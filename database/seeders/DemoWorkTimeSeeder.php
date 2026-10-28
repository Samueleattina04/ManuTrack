<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\WorkCalendar;
use App\Support\WorkTime\CalendarEditor;
use Illuminate\Database\Seeder;

/**
 * Orario di lavoro dimostrativo: due turni per tutta l'azienda, tre turni sulla linea 1,
 * dall'inizio dello storico dimostrativo. Solo dati fittizi, solo demo e sviluppo.
 */
class DemoWorkTimeSeeder extends Seeder
{
    public function run(): void
    {
        if (! DatabaseSeeder::demoAllowed() || WorkCalendar::query()->exists()) {
            return;
        }
        $editor = app(CalendarEditor::class);
        $from = today()->subMonthsNoOverflow(6)->startOfMonth()->toDateString();

        $two = $editor->create('Due turni', 'due_turni');
        $editor->assign('azienda', null, $two, $from, confirmPast: true);

        $line = Location::query()->where('name', 'Linea 1')->first();
        if ($line) {
            $three = $editor->create('Tre turni', 'tre_turni');
            $editor->assign('ubicazione', $line, $three, $from, confirmPast: true);
        }
    }
}
