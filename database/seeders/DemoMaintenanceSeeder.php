<?php

namespace Database\Seeders;

use App\Actions\Maintenance\CreateWorkOrder;
use App\Actions\Maintenance\SubmitWorkRequest;
use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Maintenance\TriageWorkRequest;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\FailureCode;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Persone, segnalazioni e ordini di prova, tutti inventati.
 */
class DemoMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        if (WorkOrder::query()->exists() || ! Asset::query()->exists()) {
            return;
        }

        $people = [];
        foreach ([
            ['Responsabile Demo', 'responsabile@example.com', Access::MANAGER, 'Responsabile manutenzione'],
            ['Tecnico Uno', 'tecnico1@example.com', Access::TECHNICIAN, 'Manutentore meccanico'],
            ['Tecnico Due', 'tecnico2@example.com', Access::TECHNICIAN, 'Manutentore elettrico'],
            ['Operatore Demo', 'operatore@example.com', Access::REQUESTER, 'Operatore di linea'],
        ] as [$name, $email, $role, $job]) {
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(DatabaseSeeder::DEMO_PASSWORD)]);
            $user->forceFill(['email_verified_at' => now(), 'job_title' => $job])->save();
            $user->syncRoles([$role]);
            $people[$role][] = $user;
        }
        [$manager, $tech1, $tech2, $operator] = [$people[Access::MANAGER][0], $people[Access::TECHNICIAN][0], $people[Access::TECHNICIAN][1], $people[Access::REQUESTER][0]];

        foreach ([
            ['problema', 'P01', 'Perdita di olio'], ['problema', 'P02', 'Rumore o vibrazione anomala'], ['problema', 'P03', 'Non si avvia'], ['problema', 'P04', 'Allarme o errore sul pannello'],
            ['causa', 'C01', 'Usura'], ['causa', 'C02', 'Guarnizione danneggiata'], ['causa', 'C03', 'Componente elettrico guasto'], ['causa', 'C04', 'Uso non corretto'],
            ['rimedio', 'R01', 'Sostituzione componente'], ['rimedio', 'R02', 'Regolazione'], ['rimedio', 'R03', 'Pulizia e lubrificazione'], ['rimedio', 'R04', 'Riparazione provvisoria'],
        ] as [$kind, $code, $label]) {
            FailureCode::query()->firstOrCreate(['kind' => $kind, 'code' => $code], ['label' => $label]);
        }

        $template = ChecklistTemplate::query()->firstOrCreate(['name' => 'Rimessa in servizio dopo intervento']);
        if (! $template->items()->exists()) {
            foreach ([
                ['Zona di lavoro pulita e attrezzi rimossi', 'spunta', null, true],
                ['Protezioni rimontate e verificate', 'spunta', null, true],
                ['Pressione circuito idraulico', 'valore', 'bar', true],
                ['Prova a vuoto (10 cicli)', 'spunta', null, true],
                ['Foto della riparazione', 'spunta', null, false],
            ] as $i => [$label, $kind, $unit, $required]) {
                $template->items()->create(['position' => $i + 1, 'label' => $label, 'kind' => $kind, 'unit' => $unit, 'required' => $required]);
            }
        }
        settings()->set('costs.hourly_rate', 38.5);

        $asset = fn (string $code) => Asset::query()->where('code', $code)->firstOrFail();
        $submit = app(SubmitWorkRequest::class);
        $triage = app(TriageWorkRequest::class);
        $create = app(CreateWorkOrder::class);
        $move = app(TransitionWorkOrder::class);

        // Da valutare
        $submit->handle($operator, ['asset_id' => $asset('NAS-01')->id, 'description' => 'Il nastro slitta quando è carico e fa un rumore forte vicino al motore.', 'priority' => 'alta', 'machine_stopped' => false, 'reported_at' => now()->subMinutes(40)->toIso8601String()]);
        $submit->handle($operator, ['asset_id' => $asset('CMP-02')->id, 'description' => 'Piccola perdita d\'aria dal raccordo di uscita.', 'priority' => 'bassa', 'machine_stopped' => false, 'reported_at' => now()->subHours(5)->toIso8601String()]);

        // Accettata e in corso
        $r = $submit->handle($operator, ['asset_id' => $asset('PRS-02')->id, 'description' => 'Pressa ferma con allarme di sovrapressione, perde olio dal cilindro principale.', 'priority' => 'critica', 'machine_stopped' => true, 'reported_at' => now()->subHours(26)->toIso8601String()])['request'];
        $wo = $triage->accept($r, $manager, ['priority' => 'critica', 'assignees' => [$tech1->id], 'checklist_template_id' => $template->id, 'due_at' => now()->addDay()->toDateString()]);
        $move->handle($wo, WorkOrderStatus::InProgress, $tech1);
        $wo->comments()->create(['user_id' => $tech1->id, 'body' => 'Guarnizione del cilindro da sostituire: ordinata al fornitore.']);
        $move->handle($wo->refresh(), WorkOrderStatus::Waiting, $tech1, ['waiting_reason' => 'ricambi']);

        // Pianificati e da fare
        $create->handle($manager, ['type' => 'miglioramento', 'title' => 'Aggiungere un sensore di livello al serbatoio olio', 'asset_id' => $asset('PRS-03')->id, 'priority' => 'media', 'assignees' => [$tech2->id], 'due_at' => now()->addWeeks(2)->toDateString()]);
        $create->handle($manager, ['type' => 'correttivo', 'title' => 'Sostituire la lampada di segnalazione guasta', 'asset_id' => $asset('ROB-02')->id, 'priority' => 'bassa']);
        $create->handle($manager, ['type' => 'correttivo', 'title' => 'Controllare il serraggio dei morsetti del quadro', 'asset_id' => $asset('QEL-01')->id, 'priority' => 'alta', 'assignees' => [$tech2->id], 'due_at' => now()->subDay()->toDateString()]);

        // Completato e chiuso
        $done = $create->handle($manager, ['type' => 'correttivo', 'title' => 'Sostituire la cinghia del ventilatore', 'asset_id' => $asset('CMP-01')->id, 'priority' => 'media', 'assignees' => [$tech1->id]]);
        $move->handle($done, WorkOrderStatus::InProgress, $tech1);
        $done->timeEntries()->update(['started_at' => now()->subHours(3), 'ended_at' => now()->subHours(2), 'minutes' => 55]);
        $move->handle($done->refresh(), WorkOrderStatus::Completed, $tech1, [
            'completion_notes' => 'Cinghia sostituita e tensionata, prova di 30 minuti senza vibrazioni.',
            'failure_problem_id' => FailureCode::query()->where('code', 'P02')->value('id'),
            'failure_cause_id' => FailureCode::query()->where('code', 'C01')->value('id'),
            'failure_remedy_id' => FailureCode::query()->where('code', 'R01')->value('id'),
        ]);
        $move->handle($done->refresh(), WorkOrderStatus::Closed, $manager);
    }
}
