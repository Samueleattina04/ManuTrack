<?php

namespace App\Livewire\Assets;

use App\Support\Assets\AssetImport;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Importazione delle macchine da Excel o CSV: carica, controlla l'anteprima, conferma.
 */
class Import extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public string $fileName = '';

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array{created: int, updated: int}|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('assets.manage');
    }

    public function updatedFile(AssetImport $import): void
    {
        $this->authorize('assets.manage');
        $this->resetErrorBag();
        $this->reset('preview', 'result');
        if (! $this->file) {
            return;
        }

        $this->fileName = $this->file->getClientOriginalName();
        try {
            $rows = $import->read((string) $this->file->getRealPath(), $this->file->getClientOriginalExtension());
            $this->preview = $import->analyze($rows);
        } catch (RuntimeException $e) {
            $this->addError('file', $e->getMessage());
        } finally {
            $this->file->delete();
            $this->file = null;
        }
    }

    public function confirm(AssetImport $import): void
    {
        $this->authorize('assets.manage');
        if (! $this->preview || ($this->preview['create'] + $this->preview['update']) === 0) {
            return;
        }

        try {
            $this->result = $import->apply($this->preview['token'], auth()->user());
        } catch (RuntimeException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }
        $this->preview = null;
    }

    public function restart(): void
    {
        $this->reset('preview', 'result', 'fileName');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.assets.import', [
            'xlsx' => AssetImport::xlsxAvailable(),
            'max' => AssetImport::MAX_ROWS,
            'columns' => AssetImport::TEMPLATE_HEADERS,
        ])->title(__('assets.import.title'))->layoutData(['heading' => __('assets.import.title')]);
    }
}
