<?php

namespace App\Actions\Maintenance;

use App\Actions\Assets\ChangeAssetStatus;
use App\Actions\Assets\StoreAttachment;
use App\Enums\AssetStatus;
use App\Enums\AttachmentKind;
use App\Enums\Priority;
use App\Models\Asset;
use App\Models\DowntimeEvent;
use App\Models\User;
use App\Models\WorkRequest;
use App\Support\Audit\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Registra una segnalazione (dal telefono, anche inviata in ritardo dalla coda offline).
 *
 * - Lo stesso client_uuid inviato due volte restituisce la segnalazione già salvata.
 * - "Macchina ferma": la macchina passa in guasto e si apre un fermo dall'ora della segnalazione.
 * - Avvisa chi valuta le segnalazioni: subito se è urgente, altrimenti dalla coda.
 */
class SubmitWorkRequest
{
    public const MAX_PHOTOS = 6;

    public function __construct(
        private StoreAttachment $attachments,
        private ChangeAssetStatus $assetStatus,
        private Notify $notify,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $photos
     * @return array{request: WorkRequest, duplicate: bool, photo_errors: list<string>}
     */
    public function handle(User $user, array $input, array $photos = []): array
    {
        $data = Validator::make($input, [
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'machine_stopped' => ['boolean'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'client_uuid' => ['nullable', 'uuid'],
            'reported_at' => ['nullable', 'date'],
        ], [], [
            'asset_id' => __('maintenance.field.asset'),
            'description' => __('maintenance.field.description'),
            'priority' => __('maintenance.field.priority'),
        ])->validate();

        // Invio ripetuto dalla coda offline: niente doppioni
        if (! empty($data['client_uuid']) && ($existing = WorkRequest::query()->where('client_uuid', $data['client_uuid'])->first())) {
            if ($existing->user_id !== $user->id) {
                throw new RuntimeException(__('maintenance.request.uuid_conflict'));
            }

            return ['request' => $existing, 'duplicate' => true, 'photo_errors' => []];
        }

        $reportedAt = $this->reportedAt($data['reported_at'] ?? null);
        $asset = ! empty($data['asset_id']) ? Asset::query()->find($data['asset_id']) : null;

        $request = DB::transaction(function () use ($user, $data, $reportedAt, $asset) {
            $request = new WorkRequest([
                'asset_id' => $asset?->id,
                'location_id' => $asset ? $asset->location_id : ($data['location_id'] ?? null),
                'description' => trim((string) $data['description']),
                'machine_stopped' => (bool) ($data['machine_stopped'] ?? false) && $asset !== null,
                'priority' => $data['priority'],
                'client_uuid' => $data['client_uuid'] ?? null,
                'reported_at' => $reportedAt,
            ]);
            $request->user_id = $user->id;
            $request->save();

            if ($request->machine_stopped && $asset) {
                if (! in_array($asset->status, [AssetStatus::Fault, AssetStatus::Stopped], true)) {
                    $this->assetStatus->handle($asset, AssetStatus::Fault, $user, $request->number, notify: false);
                }
                if (! DowntimeEvent::query()->where('asset_id', $asset->id)->open()->exists()) {
                    DowntimeEvent::query()->create([
                        'asset_id' => $asset->id,
                        'work_request_id' => $request->id,
                        'started_at' => $reportedAt,
                    ]);
                }
            }

            return $request;
        });

        $photoErrors = [];
        foreach (array_slice($photos, 0, self::MAX_PHOTOS) as $photo) {
            try {
                $saved = $this->attachments->handle($request, $photo, AttachmentKind::Photo);
                if ($saved->notice) {
                    $photoErrors[] = $photo->getClientOriginalName().': '.$saved->notice;
                }
            } catch (RuntimeException $e) {
                $photoErrors[] = $photo->getClientOriginalName().': '.$e->getMessage();
            }
        }

        Audit::log('requests.created', $request, $request->number.' · '.$request->summary(120), actor: $user);
        $request->load(['asset', 'location']);
        $this->notify->newRequest($request, $user);

        return ['request' => $request, 'duplicate' => false, 'photo_errors' => $photoErrors];
    }

    /**
     * Ora scritta sul telefono: si accetta se plausibile (non nel futuro, non più vecchia di 30 giorni).
     */
    private function reportedAt(mixed $value): Carbon
    {
        try {
            $at = $value ? Carbon::parse((string) $value) : null;
        } catch (\Throwable) {
            $at = null;
        }

        if ($at === null || $at->isAfter(now()->addMinutes(2)) || $at->isBefore(now()->subDays(30))) {
            return now();
        }

        return $at->setTimezone(config('app.timezone'));
    }
}
