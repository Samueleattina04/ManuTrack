<?php

namespace App\Actions\Assets;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        return DB::transaction(function () use ($meter, $value, $user, $readAt, $reset, $note, $source) {
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
                $meter->forceFill(['current_value' => $value, 'last_read_at' => $readAt])->save();
            }

            return $reading;
        });
    }

    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
