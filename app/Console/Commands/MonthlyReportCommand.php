<?php

namespace App\Console\Commands;

use App\Enums\NotificationEvent;
use App\Models\User;
use App\Support\Kpi\MonthlyReport;
use App\Support\Notifications\Notifier;
use App\Support\Push\PushMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Il 1° del mese avvisa chi vede gli indicatori che il rapporto del mese
 * appena finito è pronto (centro avvisi, push ed email secondo le preferenze).
 * Il rapporto non viene generato né salvato: lo si apre dal link.
 */
class MonthlyReportCommand extends Command
{
    protected $signature = 'manutrack:rapporto-mensile';

    protected $description = 'Avvisa che il rapporto mensile del mese precedente è pronto';

    public function handle(Notifier $notifier): int
    {
        $month = CarbonImmutable::now()->startOfMonth()->subMonthNoOverflow();
        $label = ucfirst($month->translatedFormat('F Y'));
        $users = User::permission('reports.view')->where('is_active', true)->get();

        $notifier->send(NotificationEvent::MonthlyReport, $users, new PushMessage(
            __('kpi.report.notify_title', ['month' => $label]),
            __('kpi.report.notify_body'),
            MonthlyReport::url($month),
            'rapporto-'.$month->format('Y-m'),
        ));
        $this->components->info(__('kpi.report.notify_done', ['month' => $label, 'n' => $users->count()]));

        return self::SUCCESS;
    }
}
