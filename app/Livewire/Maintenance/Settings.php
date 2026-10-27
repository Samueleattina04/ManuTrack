<?php

namespace App\Livewire\Maintenance;

use App\Enums\FailureCodeKind;
use App\Models\ChecklistTemplate;
use App\Models\FailureCode;
use App\Support\Audit\Audit;
use App\Support\Numbers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Impostazioni della manutenzione: modelli di checklist, codici guasto e costo orario.
 */
class Settings extends Component
{
    public ?int $editingTemplate = null;

    public string $templateName = '';

    /**
     * Una voce per riga. "Pressione [bar]" chiede un valore con l'unità tra
     * parentesi quadre; un "?" all'inizio rende la voce facoltativa.
     */
    public string $templateItems = '';

    /** @var array{kind: string, code: string, label: string} */
    public array $code = ['kind' => 'problema', 'code' => '', 'label' => ''];

    public string $hourlyRate = '';

    public function mount(): void
    {
        $this->authorize('work_orders.settings');
        $rate = settings('costs.hourly_rate');
        $this->hourlyRate = $rate !== null ? number_format((float) $rate, 2, ',', '') : '';
    }

    public function editTemplate(?int $id = null): void
    {
        $this->resetErrorBag();
        $template = $id ? ChecklistTemplate::query()->with('items')->findOrFail($id) : null;
        $this->editingTemplate = $template->id ?? 0;
        $this->templateName = $template->name ?? '';
        $this->templateItems = $template
            ? $template->items->map(fn ($i) => ($i->required ? '' : '? ').$i->label.($i->kind === 'valore' ? ' ['.$i->unit.']' : ''))->implode("\n")
            : '';
    }

    public function saveTemplate(): void
    {
        $this->authorize('work_orders.settings');
        $template = $this->editingTemplate ? ChecklistTemplate::query()->findOrFail($this->editingTemplate) : new ChecklistTemplate;
        $this->validate([
            'templateName' => ['required', 'string', 'max:120', Rule::unique('checklist_templates', 'name')->ignore($template->id)],
            'templateItems' => ['required', 'string', 'max:10000'],
        ], [], ['templateName' => __('maintenance.settings.template_name'), 'templateItems' => __('maintenance.settings.template_items')]);

        $items = self::parseItems($this->templateItems);
        DB::transaction(function () use ($template, $items): void {
            $template->fill(['name' => trim($this->templateName)])->save();
            $template->items()->delete();
            foreach ($items as $i => $item) {
                $template->items()->create($item + ['position' => $i + 1]);
            }
        });
        Audit::log('work_orders.template_saved', $template, $template->name);
        $this->editingTemplate = null;
    }

    public function deleteTemplate(int $id): void
    {
        $this->authorize('work_orders.settings');
        $template = ChecklistTemplate::query()->findOrFail($id);
        Audit::log('work_orders.template_deleted', null, $template->name);
        $template->delete();
        $this->editingTemplate = null;
    }

    /**
     * @return list<array{label: string, kind: string, unit: string|null, required: bool}>
     */
    public static function parseItems(string $text): array
    {
        $items = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $required = ! str_starts_with($line, '?');
            $line = ltrim($line, "? \t");
            $unit = null;
            if (preg_match('/^(.*?)\s*\[([^\]]{1,20})\]\s*$/u', $line, $m)) {
                [$line, $unit] = [trim($m[1]), trim($m[2])];
            }
            if ($line !== '') {
                $items[] = ['label' => mb_strimwidth($line, 0, 255, ''), 'kind' => $unit !== null ? 'valore' : 'spunta', 'unit' => $unit, 'required' => $required];
            }
        }

        return $items;
    }

    public function addCode(): void
    {
        $this->authorize('work_orders.settings');
        $this->validate([
            'code.kind' => ['required', Rule::enum(FailureCodeKind::class)],
            'code.code' => ['required', 'string', 'max:20', Rule::unique('failure_codes', 'code')->where('kind', $this->code['kind'])],
            'code.label' => ['required', 'string', 'max:120'],
        ], [], ['code.code' => __('maintenance.settings.code'), 'code.label' => __('maintenance.settings.code_label')]);
        FailureCode::query()->create(['kind' => $this->code['kind'], 'code' => strtoupper(trim($this->code['code'])), 'label' => trim($this->code['label'])]);
        $this->code = ['kind' => $this->code['kind'], 'code' => '', 'label' => ''];
    }

    public function toggleCode(int $id): void
    {
        $this->authorize('work_orders.settings');
        $code = FailureCode::query()->findOrFail($id);
        // I codici usati negli ordini non si cancellano: si disattivano
        $code->forceFill(['is_active' => ! $code->is_active])->save();
    }

    public function saveRate(): void
    {
        $this->authorize('work_orders.settings');
        $value = $this->hourlyRate === '' ? null : Numbers::parse($this->hourlyRate);
        if ($this->hourlyRate !== '' && ($value === null || $value < 0 || $value > 10000)) {
            $this->addError('hourlyRate', __('assets.meters.invalid'));

            return;
        }
        settings()->set('costs.hourly_rate', $value);
        Audit::log('settings.hourly_rate', null, $value === null ? '—' : number_format($value, 2, ',', '.').' €/h');
        $this->dispatch('toast', message: __('maintenance.settings.rate_saved'));
    }

    public function render(): View
    {
        return view('livewire.maintenance.settings', [
            'templates' => ChecklistTemplate::query()->withCount('items')->orderBy('name')->get(),
            'codes' => FailureCode::query()->orderBy('code')->get()->groupBy(fn (FailureCode $c) => $c->kind->value),
            'kinds' => FailureCodeKind::cases(),
        ])->title(__('maintenance.settings.title'))->layoutData(['heading' => __('maintenance.settings.title')]);
    }
}
