<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Scrive nel registro delle attività chi ha fatto cosa, quando e da dove.
 * Non blocca mai l'azione principale: se il registro non è scrivibile si annota nel log.
 */
class Audit
{
    /** Campi che non vengono mai salvati nel registro */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token', 'token_hash'];

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $changes = [], ?User $actor = null): void
    {
        try {
            $actor ??= auth()->user();
            $request = app()->runningInConsole() ? null : request();

            AuditLog::query()->create([
                'user_id' => $actor?->getKey(),
                'user_name' => $actor ? $actor->name : (app()->runningInConsole() ? 'console' : null),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description ? mb_strimwidth($description, 0, 255, '…') : null,
                'changes' => $changes === [] ? null : self::clean($changes),
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_strimwidth((string) $request->userAgent(), 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Differenze tra prima e dopo per un modello appena salvato.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(Model $model): array
    {
        $out = [];
        // Dopo il salvataggio getOriginal() contiene già i valori nuovi: i vecchi sono in getPrevious()
        $previous = $model->getPrevious();
        foreach (Arr::except($model->getChanges(), ['updated_at', ...self::HIDDEN]) as $key => $new) {
            $out[$key] = [$previous[$key] ?? null, $new];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function clean(array $changes): array
    {
        return Arr::except($changes, self::HIDDEN);
    }
}
