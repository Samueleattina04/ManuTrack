<?php

use App\Enums\NotificationEvent as E;
use App\Livewire\Notifications\Index;
use App\Livewire\Notifications\Preferences;
use App\Livewire\Settings\Notifications as Rules;
use App\Models\NotificationPreference;
use App\Models\UserNotification;
use App\Notifications\DigestMail;
use App\Notifications\EventMail;
use App\Support\Access\Access;
use App\Support\Notifications\Notifier;
use App\Support\Push\PushMessage;
use App\Support\Push\PushTransport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\RecordingPushTransport;

beforeEach(function () {
    $this->admin = installManuTrack();
    config(['manutrack.webpush.public_key' => 'pubblica', 'manutrack.webpush.private_key' => 'privata', 'queue.default' => 'sync']);
    $this->transport = new RecordingPushTransport;
    $this->app->instance(PushTransport::class, $this->transport);
    Notification::fake();
    $this->keeper = userWithRole(Access::STOREKEEPER);
    $this->manager = userWithRole(Access::MANAGER);
    $this->notifier = app(Notifier::class);
});

function msg(string $title = 'Ricambio sotto scorta', ?string $tag = 'part-1'): PushMessage
{
    return new PushMessage($title, 'Cuscinetto: ne restano 2', '/magazzino', $tag);
}

it('salva sempre l\'avviso nell\'app e usa i canali predefiniti dell\'evento', function () {
    $this->notifier->send(E::StockLow, [$this->keeper, $this->manager], msg());

    expect(UserNotification::query()->count())->toBe(2)
        // Scorta minima: push sì, email nel riepilogo
        ->and(UserNotification::query()->where('digest', true)->count())->toBe(2);
    Notification::assertNothingSent();
});

it('email per ruolo decisa dall\'amministratore; la scelta personale vince', function () {
    Livewire::actingAs($this->admin)->test(Rules::class)->set('rules.'.Access::STOREKEEPER.'.'.E::StockLow->value, 'subito')->call('save');

    $this->notifier->send(E::StockLow, [$this->keeper, $this->manager], msg());
    Notification::assertSentTo($this->keeper, EventMail::class, fn (EventMail $m) => $m->event === E::StockLow);
    Notification::assertNotSentTo($this->manager, EventMail::class);

    NotificationPreference::query()->create(['user_id' => $this->keeper->id, 'event' => E::StockLow->value, 'email' => 'no', 'push' => false]);
    Notification::fake();
    $this->transport->sent = [];
    $this->notifier->send(E::StockLow, [$this->keeper], msg());
    Notification::assertNothingSent();
    expect($this->transport->sent)->toBe([]);
});

it('le preferenze mostrano solo gli eventi possibili e salvano solo le differenze', function () {
    $tech = userWithRole(Access::TECHNICIAN);
    $page = Livewire::actingAs($tech)->test(Preferences::class);
    expect(array_keys($page->get('prefs')))->toContain(E::OrderAssigned->value)->not->toContain(E::StockLow->value)->not->toContain(E::SystemUpdate->value);

    $page->set('prefs.'.E::OrderAssigned->value.'.email', 'subito')->call('save');
    expect(NotificationPreference::query()->where('user_id', $tech->id)->count())->toBe(1);

    // Un evento non permesso, aggiunto a mano, viene ignorato
    $page->set('prefs.'.E::SystemUpdate->value, ['push' => false, 'email' => 'subito'])->call('save');
    expect(NotificationPreference::query()->where('user_id', $tech->id)->where('event', E::SystemUpdate->value)->exists())->toBeFalse();
});

it('centro avvisi: raggruppa per tag, apre e segna letto, solo i propri', function () {
    $this->notifier->send(E::StockLow, [$this->keeper], msg('Primo'));
    $this->notifier->send(E::StockLow, [$this->keeper], msg('Secondo'));
    $this->notifier->send(E::StockLow, [$this->manager], msg('Di altri', 'altro'));

    $this->actingAs($this->keeper)->get(route('notifications.index'))->assertOk()->assertSee('Secondo')->assertSee('× 2')->assertDontSee('Di altri');
    $this->actingAs($this->keeper)->getJson(route('notifications.count'))->assertJson(['unread' => 2]);
    $this->actingAs($this->keeper)->get('/')->assertSee('data-unread="2"', false);

    $mine = UserNotification::query()->where('user_id', $this->keeper->id)->latest('id')->first();
    Livewire::actingAs($this->keeper)->test(Index::class)->call('open', $mine->id)->assertRedirect('/magazzino');
    expect(UserNotification::unreadCount($this->keeper))->toBe(0);   // letti insieme (stesso tag)

    $other = UserNotification::query()->where('user_id', $this->manager->id)->sole();
    expect(fn () => Livewire::actingAs($this->keeper)->test(Index::class)->call('open', $other->id))->toThrow(ModelNotFoundException::class);

    Livewire::actingAs($this->manager)->test(Index::class)->call('markAllRead');
    expect(UserNotification::unreadCount($this->manager))->toBe(0);
});

it('riepilogo giornaliero: una email con gli avvisi non ancora inviati, poi pulizia dei vecchi', function () {
    $this->notifier->send(E::StockLow, [$this->keeper], msg('Uno', 'a'));
    $this->notifier->send(E::StockLow, [$this->keeper], msg('Due', 'b'));
    $old = UserNotification::query()->create(['user_id' => $this->keeper->id, 'event' => E::StockLow, 'title' => 'Vecchio']);
    $old->forceFill(['created_at' => now()->subDays(100)])->save();

    $this->artisan('manutrack:avvisi-riepilogo')->assertSuccessful();
    Notification::assertSentTo($this->keeper, DigestMail::class, fn (DigestMail $m) => count($m->items) === 2);
    expect(UserNotification::query()->where('digest', true)->whereNull('emailed_at')->count())->toBe(0)
        ->and(UserNotification::query()->whereKey($old->id)->exists())->toBeFalse();

    Notification::fake();
    $this->artisan('manutrack:avvisi-riepilogo')->assertSuccessful();
    Notification::assertNothingSent();
});

it('le regole email per ruolo sono solo per chi gestisce le impostazioni', function () {
    $this->actingAs($this->manager)->get(route('settings.notifications'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('settings.notifications'))->assertOk()->assertSee(E::StockLow->label());
});

it('le persone disattivate non ricevono nulla', function () {
    $this->keeper->forceFill(['is_active' => false])->save();
    $this->notifier->send(E::StockLow, [$this->keeper], msg());
    expect(UserNotification::query()->count())->toBe(0);
});
