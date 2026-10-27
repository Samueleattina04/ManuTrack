<?php

namespace App\Livewire\WorkOrders;

use App\Actions\Maintenance\CreateWorkOrder;
use App\Enums\Priority;
use App\Enums\WorkOrderType;
use App\Models\Asset;
use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Nuovo ordine di lavoro senza segnalazione (es. un miglioramento, o un
 * correttivo deciso direttamente dal tecnico). Chi non può assegnare se lo
 * assegna da solo.
 */
class Create extends Component
{
    /** @var array<string, mixed> */
    public array $form = [
        'type' => 'correttivo', 'title' => '', 'description' => '', 'asset_id' => '', 'priority' => 'media',
        'due_at' => '', 'checklist_template_id' => '', 'assignees' => [],
    ];

    public function mount(): void
    {
        $this->authorize('work_orders.create');
        if ($asset = Asset::query()->find(request()->integer('macchina'))) {
            $this->form['asset_id'] = (string) $asset->id;
        }
        if (! auth()->user()->can('work_orders.assign') && auth()->user()->can('work_orders.execute')) {
            $this->form['assignees'] = [(string) auth()->id()];
        }
    }

    public function save(CreateWorkOrder $create): RedirectResponse|Redirector|null
    {
        $this->authorize('work_orders.create');
        $user = auth()->user();
        $input = array_map(fn ($v) => $v === '' ? null : $v, $this->form);
        $input['assignees'] = $user->can('work_orders.assign')
            ? array_map('intval', (array) $this->form['assignees'])
            : ($user->can('work_orders.execute') ? [$user->id] : []);

        try {
            $order = $create->handle($user, $input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return null;
        }

        session()->flash('status', __('maintenance.order.created', ['number' => $order->number]));

        return redirect()->route('work-orders.show', $order);
    }

    public function render(): View
    {
        $canAssign = auth()->user()->can('work_orders.assign');

        return view('livewire.work-orders.create', [
            'types' => WorkOrderType::cases(),
            'priorities' => Priority::cases(),
            'assets' => Asset::query()->where('status', '!=', 'dismessa')->orderBy('code')->limit(3000)->get(['id', 'code', 'name']),
            'templates' => ChecklistTemplate::query()->orderBy('name')->pluck('name', 'id'),
            'executors' => $canAssign ? User::permission('work_orders.execute')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'canAssign' => $canAssign,
        ])->title(__('maintenance.order.new'))->layoutData(['heading' => __('maintenance.order.new')]);
    }
}
