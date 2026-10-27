<?php

namespace App\Actions\Assets;

use App\Actions\Preventive\GeneratePlanOrders;
use App\Models\MaintenancePlan;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registra una lettura di un contatore.
 * Nei contatori progressivi una lettura non può essere più bassa della
 * precedente, a meno che il contatore sia stato sostituito o azzerato.
 */
class RecordMeterReading
{
    public function handle(Meter $meter, float $value, ?User $user, ?Carbon $readAt = null, bool $reset = false, ?string $note = null, string $source = 'manuale'): MeterReading
    {
        $readAt ??= now();

        if ($value < 0) {
            throw ValidationException::withMessages(['value' => __('assets.meters.negative')]);
        }
        if ($readAt->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['value' => __('assets.meters.future')]);
        }

        $reading = DB::transaction(function () use ($meter, $value, $user, $readAt, $reset, $note, $source) {
            /** @var Meter $meter */
            $meter = Meter::query()->lockForUpdate()->findOrFail($meter->id);

            if ($meter->cumulative && ! $reset) {
                $before = $meter->readings()->where('read_at', '<=', $readAt)->first();
                $after = $meter->readings()->where('read_at', '>', $readAt)->reorder('read_at')->first();
                if ($before && $value < (float) $before->value) {
                    throw ValidationException::withMessages(['value' => __('assets.meters.lower', ['value' => self::format((float) $before->value), 'unit' => $meter->unit])]);
                }
                if ($after && ! $after->is_reset && $value > (float) $after->value) {
                    throw ValidationException::withMessages(['value' => __('assets.meters.higher', ['value' => self::format((float) $after->value), 'unit' => $meter->unit])]);
                }
            }

            $reading = $meter->readings()->create([
                'value' => $value,
                'read_at' => $readAt,
                'user_id' => $user?->id,
                'source' => $source,
                'is_reset' => $reset,
                'note' => $note ? mb_strimwidth($note, 0, 255, '') : null,
            ]);

            if ($meter->last_read_at === null || ! $readAt->isBefore($meter->last_read_at)) {
                // Contatore sostituito o azzerato: le soglie dei piani ripartono dal nuovo valore
                if ($reset) {
                    $this->rebasePlans($meter, (float) $meter->current_value, $value);
                }
                $meter->forceFill(['current_value' => $value, 'last_read_at' => $readAt])->save();
            }

            return $reading;
        });

        // Piani a contatore arrivati alla soglia: l'ordine nasce subito, senza aspettare il cron
        // (un problema nella generazione non deve far perdere la lettura, già salvata)
        try {
            app(GeneratePlanOrders::class)->forMeter($meter->refresh());
        } catch (Throwable $e) {
            report($e);
        }

        return $reading;
    }

    /** Mantiene quanto mancava alla soglia: 4.800 su 5.000 h, azzerato a 0 → nuova soglia 200 */
    private function rebasePlans(Meter $meter, float $old, float $new): void
    {
        MaintenancePlan::query()->where('meter_id', $meter->id)->whereNotNull('next_meter_due')->lockForUpdate()->get()
            ->each(fn (MaintenancePlan $p) => $p->forceFill(['next_meter_due' => round($new + max(0, (float) $p->next_meter_due - $old), 2)])->save());
    }

    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
