<?php

namespace App\Livewire\Assets;

use App\Actions\Assets\ChangeAssetStatus;
use App\Actions\Assets\RecordMeterReading;
use App\Actions\Assets\StoreAttachment;
use App\Enums\AssetStatus;
use App\Enums\AttachmentKind;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Meter;
use App\Support\Assets\QrCode;
use App\Support\Audit\Audit;
use App\Support\Files\PhotoProcessor;
use App\Support\Numbers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Scheda di una macchina: dati, stato, contatori, foto, documenti, sottogruppi e QR.
 */
class Show extends Component
{
    use WithFileUploads;

    public Asset $asset;

    // Cambio di stato
    public string $newStatus = '';

    public string $statusNote = '';

    // Contatori
    /** @var array<int, string> Valore in scrittura per ciascun contatore */
    public array $reading = [];

    /** @var array<int, bool> */
    public array $readingReset = [];

    public ?int $historyFor = null;

    public ?int $editingMeter = null;

    /** @var array{name: string, unit: string, cumulative: bool} */
    public array $meterForm = ['name' => '', 'unit' => 'h', 'cumulative' => true];

    // Allegati
    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    /** @var list<TemporaryUploadedFile> */
    public array $documents = [];

    public function mount(Asset $asset): void
    {
        $this->authorize('assets.view');
        $this->asset = $asset;
    }

    public function changeStatus(ChangeAssetStatus $change): void
    {
        $this->authorize('assets.status');
        $this->validate([
            'newStatus' => ['required', Rule::enum(AssetStatus::class)],
            'statusNote' => ['nullable', 'string', 'max:200'],
        ], attributes: ['newStatus' => __('assets.field.status')]);

        $status = AssetStatus::from($this->newStatus);
        $change->handle($this->asset, $status, auth()->user(), trim($this->statusNote) ?: null);
        $this->asset->refresh();
        $this->reset('newStatus', 'statusNote');
        $this->dispatch('toast', message: __('assets.status.changed', ['status' => $status->label()]));
    }

    public function recordReading(int $meterId, RecordMeterReading $record): void
    {
        $this->authorize('assets.readings');
        $meter = $this->meter($meterId);
        $value = Numbers::parse($this->reading[$meterId] ?? '');
        if ($value === null) {
            $this->addError('reading.'.$meterId, __('assets.meters.invalid'));

            return;
        }

        try {
            $record->handle($meter, $value, auth()->user(), reset: (bool) ($this->readingReset[$meterId] ?? false));
        } catch (ValidationException $e) {
            $this->addError('reading.'.$meterId, (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->reading[$meterId], $this->readingReset[$meterId]);
        $this->dispatch('toast', message: __('assets.meters.saved'));
    }

    public function toggleHistory(int $meterId): void
    {
        $this->historyFor = $this->historyFor === $meterId ? null : $meterId;
    }

    public function editMeter(?int $meterId = null): void
    {
        $this->authorize('assets.manage');
        $this->resetErrorBag();
        $meter = $meterId ? $this->meter($meterId) : null;
        $this->editingMeter = $meterId ?? 0;
        $this->meterForm = $meter
            ? ['name' => $meter->name, 'unit' => $meter->unit, 'cumulative' => $meter->cumulative]
            : ['name' => '', 'unit' => 'h', 'cumulative' => true];
    }

    public function saveMeter(): void
    {
        $this->authorize('assets.manage');
        $meter = $this->editingMeter ? $this->meter($this->editingMeter) : new Meter;
        $this->validate([
            'meterForm.name' => ['required', 'string', 'max:80', Rule::unique('meters', 'name')->where('asset_id', $this->asset->id)->ignore($meter->id)],
            'meterForm.unit' => ['required', 'string', 'max:20'],
            'meterForm.cumulative' => ['boolean'],
        ], attributes: ['meterForm.name' => __('assets.meters.name'), 'meterForm.unit' => __('assets.meters.unit')]);

        $meter->fill($this->meterForm);
        $meter->asset_id = $this->asset->id;
        $meter->save();
        Audit::log('assets.meter_saved', $this->asset, $meter->name.' ('.$meter->unit.')');
        $this->editingMeter = null;
    }

    public function deleteMeter(int $meterId): void
    {
        $this->authorize('assets.manage');
        $meter = $this->meter($meterId);
        Audit::log('assets.meter_deleted', $this->asset, $meter->name);
        $meter->delete();
        $this->editingMeter = null;
    }

    public function updatedPhotos(StoreAttachment $store): void
    {
        $this->storeFiles($this->photos, AttachmentKind::Photo, $store, 'photos');
        $this->photos = [];
    }

    public function updatedDocuments(StoreAttachment $store): void
    {
        $this->storeFiles($this->documents, AttachmentKind::Document, $store, 'documents');
        $this->documents = [];
    }

    /**
     * @param  list<TemporaryUploadedFile>  $files
     */
    private function storeFiles(array $files, AttachmentKind $kind, StoreAttachment $store, string $field): void
    {
        $this->authorize('assets.attach');
        $saved = 0;
        foreach ($files as $file) {
            try {
                $attachment = $store->handle($this->asset, $file, $kind);
                $saved++;
                if ($attachment->notice) {
                    $this->addError($field, $file->getClientOriginalName().': '.$attachment->notice);
                }
            } catch (RuntimeException $e) {
                $this->addError($field, $file->getClientOriginalName().': '.$e->getMessage());
            } finally {
                $file->delete();
            }
        }
        if ($saved > 0) {
            $this->dispatch('toast', message: trans_choice('assets.files.saved', $saved, ['count' => $saved]));
        }
        $this->asset->unsetRelation('cover');
    }

    public function makeCover(int $attachmentId): void
    {
        $this->authorize('assets.attach');
        $attachment = $this->attachment($attachmentId);
        DB::transaction(function () use ($attachment): void {
            $this->asset->attachments()->where('kind', AttachmentKind::Photo->value)->increment('position');
            $attachment->forceFill(['position' => 0])->save();
        });
        $this->asset->unsetRelation('cover');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = $this->attachment($attachmentId);
        // Chi gestisce le macchine elimina tutto; gli altri solo i propri file
        abort_unless(auth()->user()->can('assets.manage') || (auth()->user()->can('assets.attach') && $attachment->user_id === auth()->id()), 403);
        Audit::log('assets.file_deleted', $this->asset, $attachment->original_name);
        $attachment->delete();
        $this->asset->unsetRelation('cover');
    }

    public function regenerateQr(): void
    {
        $this->authorize('assets.manage');
        $this->asset->forceFill(['qr_token' => Asset::newQrToken()])->save();
        Audit::log('assets.qr_regenerated', $this->asset, $this->asset->code);
        $this->dispatch('toast', message: __('assets.qr.regenerated'));
    }

    public function delete(): RedirectResponse|Redirector|null
    {
        $this->authorize('assets.manage');
        if ($this->asset->children()->exists()) {
            $this->dispatch('toast', message: __('assets.errors.has_children'), tone: 'ko');

            return null;
        }

        DB::transaction(function (): void {
            $this->asset->attachments()->get()->each->delete();
            $this->asset->delete();
        });
        Audit::log('assets.deleted', null, $this->asset->code.' · '.$this->asset->name);
        session()->flash('status', __('assets.deleted', ['code' => $this->asset->code]));

        return redirect()->route('assets.index');
    }

    private function meter(int $id): Meter
    {
        return $this->asset->meters()->whereKey($id)->firstOrFail();
    }

    private function attachment(int $id): Attachment
    {
        return $this->asset->attachments()->whereKey($id)->firstOrFail();
    }

    public function render(): View
    {
        $this->asset->load(['category', 'location', 'parent', 'children.category', 'children.cover', 'cover']);
        $user = auth()->user();
        $attachments = $this->asset->attachments()->with('user')->get();
        $meters = $this->asset->meters()->get();

        return view('livewire.assets.show', [
            'gallery' => $attachments->where('kind', AttachmentKind::Photo)->values(),
            'docs' => $attachments->where('kind', AttachmentKind::Document)->values(),
            'meters' => $meters,
            'history' => $this->historyFor ? $meters->firstWhere('id', $this->historyFor)?->readings()->with('user')->limit(15)->get() : null,
            'statuses' => AssetStatus::cases(),
            'qr' => QrCode::svg($this->asset->qrUrl(), title: __('assets.qr.title').' '.$this->asset->code),
            'maxMb' => StoreAttachment::maxMegabytes(),
            'photoFormat' => PhotoProcessor::outputFormat(),
            // Storico: ultimi ordini e segnalazioni ancora da valutare
            'orderHistory' => $user->can('work_orders.view')
                ? $this->asset->workOrders()->with('assignees')->latest('id')->limit(8)->get()
                : collect(),
            'historyCount' => $user->can('work_orders.view') ? $this->asset->workOrders()->count() : 0,
            'openRequests' => $this->asset->workRequests()->where('status', 'nuova')->when(! $user->can('requests.view_all'), fn ($q) => $q->where('user_id', $user->id))->latest('id')->get(),
            'downtimeMinutes' => (int) $this->asset->downtimes()->where('started_at', '>=', now()->subDays(90))->get()->sum(fn ($d) => $d->minutes()),
            'warrantyExpired' => $this->asset->warranty_until?->isPast() ?? false,
            'can' => [
                'manage' => $user->can('assets.manage'),
                'status' => $user->can('assets.status'),
                'readings' => $user->can('assets.readings'),
                'attach' => $user->can('assets.attach'),
                'costs' => $user->can('work_orders.view_costs'),
            ],
            'now' => Carbon::now(),
        ])->title($this->asset->code.' · '.$this->asset->name)->layoutData(['heading' => $this->asset->code]);
    }
}
