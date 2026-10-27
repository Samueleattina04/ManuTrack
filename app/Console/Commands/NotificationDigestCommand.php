<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\DigestMail;
use Illuminate\Console\Command;

/**
 * Riepilogo giornaliero via email degli avvisi in modalità "riepilogo"
 * non ancora inviati. Poi pulisce gli avvisi più vecchi di 90 giorni.
 */
class NotificationDigestCommand extends Command
{
    protected $signature = 'manutrack:avvisi-riepilogo';

    protected $description = 'Invia il riepilogo giornaliero degli avvisi via email e pulisce quelli vecchi';

    public const MAX_ITEMS = 30;

    public function handle(): int
    {
        $sent = 0;
        $userIds = UserNotification::query()->where('digest', true)->whereNull('emailed_at')->distinct()->pluck('user_id');
        foreach (User::query()->whereIn('id', $userIds)->where('is_active', true)->get() as $user) {
            $pending = UserNotification::query()->where('user_id', $user->id)->where('digest', true)->whereNull('emailed_at')->latest('id')->get();
            if ($pending->isEmpty()) {
                continue;
            }
            $user->notify(new DigestMail(DigestMail::fromNotifications($pending->take(self::MAX_ITEMS)), max(0, $pending->count() - self::MAX_ITEMS)));
            UserNotification::query()->whereIn('id', $pending->modelKeys())->update(['emailed_at' => now()]);
            $sent++;
        }

        $pruned = UserNotification::query()->where('created_at', '<', now()->subDays(90))->delete();
        $this->components->info(__('notifications.digest.done', ['sent' => $sent, 'pruned' => $pruned]));

        return self::SUCCESS;
    }
}
