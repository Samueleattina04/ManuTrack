<?php

namespace Database\Seeders;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Gdpr\PersonAnonymizer;
use App\Support\Gdpr\Retention;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dati dimostrativi per Dati e privacy: un ex tecnico disattivato con lavoro svolto (da
 * anonimizzare a mano), uno già anonimizzato, regole di conservazione. Solo nomi inventati.
 */
class DemoPrivacySeeder extends Seeder
{
    public function run(): void
    {
        if (! DatabaseSeeder::demoAllowed() || User::query()->where('email', 'tecnico3@example.com')->exists()) {
            return;
        }

        app(Retention::class)->save(12, 24);

        $closed = WorkOrder::query()->where('status', WorkOrderStatus::Closed->value)->orderBy('closed_at')->limit(6)->get();
        $people = [
            // nome, email, mesi dalla disattivazione, anonimizzato
            ['Tecnico Tre', 'tecnico3@example.com', 2, false],
            ['Tecnico Quattro', 'tecnico4@example.com', 14, true],
        ];
        foreach ($people as $i => [$name, $email, $months, $anonymize]) {
            $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(32))]);
            $user->forceFill(['job_title' => 'Manutentore meccanico', 'phone' => '000 000 00'.($i + 1), 'email_verified_at' => now()])->save();
            $user->syncRoles([Access::TECHNICIAN]);

            // Lavoro svolto: resta attribuito anche dopo l'anonimizzazione
            foreach ($closed->slice($i * 3, 3) as $order) {
                $order->assignees()->syncWithoutDetaching([$user->id]);
                $start = $order->started_at ?? $order->created_at;
                $order->timeEntries()->create(['user_id' => $user->id, 'started_at' => $start, 'ended_at' => $start->copy()->addMinutes(45), 'minutes' => 45, 'note' => 'Intervento di '.$name]);
                $order->comments()->create(['user_id' => $user->id, 'body' => 'Fatto da '.$name.', per domande chiamare il '.$user->phone.'.']);
            }

            $user->forceFill(['is_active' => false, 'deactivated_at' => now()->subMonths($months)])->save();
            if ($anonymize) {
                app(PersonAnonymizer::class)->anonymize($user, [], null);
            }
        }
    }
}
