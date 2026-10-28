<?php

namespace App\Livewire\Gdpr;

use App\Jobs\ExportCompanyData;
use App\Models\DataExport;
use App\Support\Audit\Audit;
use App\Support\Plant\AssetModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Esportazione completa dei dati dell'azienda (portabilità, fine contratto): si prepara
 * nella coda e si scarica come ZIP con l'indice. Lo ZIP resta sul server pochi giorni.
 */
class DataExports extends Component
{
    public function mount(): void
    {
        $this->authorize('data.export');
    }

    public function start(): void
    {
        $this->authorize('data.export');
        if (DataExport::query()->whereIn('status', ['in_coda', 'in_corso'])->exists()) {
            $this->dispatch('toast', message: __('gdpr.export.already'), tone: 'warn');

            return;
        }
        $export = DataExport::query()->create(['user_id' => auth()->id(), 'status' => 'in_coda']);
        Audit::log('system.data_export_requested', $export);
        ExportCompanyData::dispatch($export->id);
    }

    public function delete(int $id): void
    {
        $this->authorize('data.export');
        $export = DataExport::query()->findOrFail($id);
        if ($export->path) {
            Storage::disk(AssetModel::disk())->delete($export->path);
        }
        Audit::log('system.data_export_deleted', $export);
        $export->delete();
    }

    public function render(): View
    {
        $exports = DataExport::query()->with('user')->latest('id')->limit(20)->get();

        return view('livewire.gdpr.data-exports', [
            'exports' => $exports,
            'pending' => $exports->contains(fn (DataExport $e) => $e->isPending()),
        ])->title(__('gdpr.export.title'))->layoutData(['heading' => __('gdpr.export.title')]);
    }
}
