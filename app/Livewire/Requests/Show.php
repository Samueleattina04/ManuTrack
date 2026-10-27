<?php

namespace App\Livewire\Requests;

use App\Actions\Maintenance\TriageWorkRequest;
use App\Enums\Priority;
use App\Enums\RequestStatus;
use App\Enums\WorkOrderType;
use App\Models\ChecklistTemplate;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Scheda di una segnalazione e valutazione: accettare (crea l'ordine di lavoro),
 * respingere o segnare come duplicata.
 */
class Show extends Component
{
    public WorkRequest $workRequest;

    /** accept | reject | duplicate */
    public string $mode = '';

    public string $priority = '';

    public string $type = 'correttivo';

    public string $title = '';

    /** @var list<int|string> */
    public array $assignees = [];

    public string $dueAt = '';

    public string $template = '';

    public string $note = '';

    public string $original = '';

    public function mount(WorkRequest $workRequest): void
    {
        $this->authorize('view', $workRequest);
        $this->workRequest = $workRequest;
        $this->priority = $workRequest->priority->value;
        $this->title = trim(($workRequest->asset ? $workRequest->asset->code.' · ' : '').$workRequest->summary(120));
    }

    public function start(string $mode): void
    {
        $this->authorize('triage', $this->workRequest);
        $this->resetErrorBag();
        $this->mode = in_array($mode, ['accept', 'reject', 'duplicate'], true) ? $mode : '';
    }

    public function accept(TriageWorkRequest $triage): RedirectResponse|Redirector|null
    {
        $this->authorize('triage', $this->workRequest);
        try {
            $order = $triage->accept($this->workRequest, auth()->user(), [
                'priority' => $this->priority,
                'type' => $this->type,
                'title' => $this->title,
                'assignees' => array_map('intval', $this->assignees),
                'due_at' => $this->dueAt ?: null,
                'checklist_template_id' => $this->template ?: null,
                'note' => $this->note,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return null;
        }
        session()->flash('status', __('maintenance.request.accepted', ['number' => $order->number]));

        return auth()->user()->can('view', $order) ? redirect()->route('work-orders.show', $order) : redirect()->route('requests.show', $this->workRequest);
    }

    public function reject(TriageWorkRequest $triage): void
    {
        $this->authorize('triage', $this->workRequest);
        try {
            $triage->reject($this->workRequest, auth()->user(), $this->note);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->mode = '';
        $this->workRequest->refresh();
    }

    public function markDuplicate(TriageWorkRequest $triage): void
    {
        $this->authorize('triage', $this->workRequest);
        try {
            $triage->duplicate($this->workRequest, auth()->user(), (int) $this->original, $this->note ?: null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->mode = '';
        $this->workRequest->refresh();
    }

    public function render(): View
    {
        $r = $this->workRequest->load(['asset.location', 'location', 'user', 'triager', 'workOrder.assignees', 'duplicateOf', 'attachments']);
        $user = auth()->user();
        $canTriage = $user->can('triage', $r);

        return view('livewire.requests.show', [
            'r' => $r,
            'canTriage' => $canTriage,
            'canSeeOrder' => $r->workOrder && $user->can('view', $r->workOrder),
            'priorities' => Priority::cases(),
            'types' => WorkOrderType::cases(),
            'executors' => $canTriage ? User::permission('work_orders.execute')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'templates' => $canTriage ? ChecklistTemplate::query()->orderBy('name')->pluck('name', 'id') : collect(),
            // Possibili originali: segnalazioni recenti sulla stessa macchina
            'candidates' => $canTriage ? WorkRequest::query()->whereKeyNot($r->id)
                ->when($r->asset_id, fn ($q) => $q->where('asset_id', $r->asset_id))
                ->whereIn('status', [RequestStatus::New->value, RequestStatus::Accepted->value])
                ->latest('id')->limit(20)->get() : collect(),
        ])->title($r->number)->layoutData(['heading' => $r->number]);
    }
}
