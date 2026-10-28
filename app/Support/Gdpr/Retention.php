<?php

namespace App\Support\Gdpr;

use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Plant\AssetModel;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Conservazione dei dati (docs/GDPR.md), applicata ogni notte da manutrack:conservazione:
 * - persone disattivate da più di N mesi: anonimizzate (N vuoto = mai);
 * - registro attività: righe più vecchie di N mesi cancellate (N vuoto = per sempre; minimo 6);
 * - esportazioni complete: ZIP cancellati dopo qualche giorno.
 */
final class Retention
{
    public const ANONYMIZE_SETTING = 'retention.anonymize_after_months';

    public const AUDIT_SETTING = 'retention.audit_months';

    public const MIN_AUDIT_MONTHS = 6;

    public function __construct(private Settings $settings, private PersonAnonymizer $anonymizer) {}

    public function anonymizeAfterMonths(): ?int
    {
        $v = $this->settings->get(self::ANONYMIZE_SETTING);

        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    public function auditMonths(): ?int
    {
        $v = $this->settings->get(self::AUDIT_SETTING);

        return is_numeric($v) && (int) $v > 0 ? max(self::MIN_AUDIT_MONTHS, (int) $v) : null;
    }

    public function save(?int $anonymizeAfter, ?int $auditMonths): void
    {
        $this->settings->set(self::ANONYMIZE_SETTING, $anonymizeAfter && $anonymizeAfter > 0 ? $anonymizeAfter : null);
        $this->settings->set(self::AUDIT_SETTING, $auditMonths && $auditMonths > 0 ? max(self::MIN_AUDIT_MONTHS, $auditMonths) : null);
    }

    /** @return Builder<User> */
    public function usersToAnonymize(): Builder
    {
        $months = $this->anonymizeAfterMonths();

        return User::query()->where('is_active', false)->whereNull('anonymized_at')
            ->when($months === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($months !== null, fn ($q) => $q->whereNotNull('deactivated_at')->where('deactivated_at', '<', now()->subMonthsNoOverflow((int) $months)));
    }

    /** @return Builder<AuditLog> */
    public function auditToDelete(): Builder
    {
        $months = $this->auditMonths();

        return AuditLog::query()
            ->when($months === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($months !== null, fn ($q) => $q->where('created_at', '<', now()->subMonthsNoOverflow((int) $months)));
    }

    /**
     * @return array{anonymized: int, audit_deleted: int, exports_deleted: int}
     */
    public function run(): array
    {
        $anonymized = 0;
        foreach ($this->usersToAnonymize()->get() as $user) {
            try {
                $this->anonymizer->anonymize($user, [], null);
                $anonymized++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $auditDeleted = $this->auditToDelete()->delete();

        $exportsDeleted = 0;
        foreach (DataExport::query()->whereNotNull('path')->where('expires_at', '<', now())->get() as $export) {
            Storage::disk(AssetModel::disk())->delete((string) $export->path);
            $export->forceFill(['path' => null])->save();
            $exportsDeleted++;
        }

        $result = ['anonymized' => $anonymized, 'audit_deleted' => (int) $auditDeleted, 'exports_deleted' => $exportsDeleted];
        if (array_sum($result) > 0) {
            Audit::log('system.retention', null, __('gdpr.retention.done', $result), $result);
        }

        return $result;
    }
}
