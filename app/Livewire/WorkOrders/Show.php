<?php

namespace App\Livewire\WorkOrders;

use App\Actions\Assets\StoreAttachment;
use App\Actions\Maintenance\AssignWorkOrder;
use App\Actions\Maintenance\CorrectWorkOrderStatus;
use App\Actions\Maintenance\CreateWorkOrder;
use App\Actions\Maintenance\TransitionWorkOrder;
use App\Actions\Maintenance\WorkTimer;
use App\Enums\AttachmentKind;
use App\Enums\FailureCodeKind;
use App\Enums\Priority;
use App\Enums\WaitingReason;
use App\Enums\WorkOrderStatus;
use App\Models\ChecklistTemplate;
use App\Models\DowntimeEvent;
use App\Models\FailureCode;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use App\Support\Audit\Audit;
use App\Support\Numbers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Scheda dell'ordine di lavoro: stato e passaggi, assegnatari, checklist,
 * tempi, fermo macchina, foto, commenti e storico, costi.
 */
class Show extends Component
{
    use WithFileUploads;

    public WorkOrder $workOrder;

    /** Passaggio di stato in preparazione (con modulo): in_attesa, completato, annullato, riapertura */
    public string $pending = '';

    /** @var array<string, mixed> */
    public array $transition = [
        'note' => '', 'waiting_reason' => 'ricambi', 'completion_notes' => '',
        'failure_problem_id' => '', 'failure_cause_id' => '', 'failure_remedy_id' => '', 'asset_operational' => true,
    ];

    public bool $correcting = false;

    /** @var array{to: string, reason: string, completed_at: string, waiting_reason: string, downtime: string} */
    public array $correction = ['to' => '', 'reason' => '', 'completed_at' => '', 'waiting_reason' => 'ricambi', 'downtime' => ''];

    public bool $editingAssignees = false;

    /** @var list<int|string> */
    public array $assignees = [];

    public bool $editing = false;

    /** @var array<string, mixed> */
    public array $details = [];

    /** @var array<int, string> valori delle voci di checklist "valore" */
    public array $values = [];

    public string $newItem = '';

    public string $template = '';

    /** @var array{minutes: string, date: string, note: string} */
    public array $manualTime = ['minutes' => '', 'date' => '', 'note' => ''];

    public bool $addingTime = false;

    public string $comment = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    /** @var array<int, array{start: string, end: string}> */
    public array $downtime = [];

    public string $externalCost = '';

    public function mount(WorkOrder $workOrder): void
    {
        $this->authorize('view', $workOrder);
        $this->workOrder = $workOrder;
        $this->manualTime['date'] = now()->toDateString();
    }

    // ---------- Stato ----------

    public function move(string $to, TransitionWorkOrder $transition): void
    {
        $target = WorkOrderStatus::from($to);
        $this->authorize('transition', [$this->workOrder, $target]);
        $this->resetErrorBag();

        // Passaggi che chiedono qualcosa: prima il modulo
        $needsForm = in_array($target, [WorkOrderStatus::Waiting, WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)
            || ($target === WorkOrderStatus::InProgress && $this->workOrder->status === WorkOrderStatus::Completed);
        if ($needsForm && $this->pending !== $to) {
            $this->pending = $to;
            $this->resetErrorBag();
            $this->transition['asset_operational'] = true;

            return;
        }

        $input = $this->transition;
        foreach (['failure_problem_id', 'failure_cause_id', 'failure_remedy_id'] as $k) {
            $input[$k] = $input[$k] === '' ? null : (int) $input[$k];
        }
        $input['note'] = trim((string) $input['note']) ?: null;

        try {
            $transition->handle($this->workOrder, $target, auth()->user(), $input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('transition.'.$field, $messages[0]);
            }

            return;
        }

        $this->pending = '';
        $this->transition['note'] = '';
        $this->workOrder->refresh();
        $this->dispatch('toast', message: __('maintenance.order.moved', ['status' => $target->label()]));
    }

    public function cancelPending(): void
    {
        $this->pending = '';
        $this->resetErrorBag();
    }

    public function take(AssignWorkOrder $assign): void
    {
        $this->authorize('take', $this->workOrder);
        $assign->handle($this->workOrder, [auth()->id()], auth()->user());
        $this->workOrder->refresh();
        $this->dispatch('toast', message: __('maintenance.order.taken'));
    }

    // ---------- Correggi stato (amministratore) ----------

    public function startCorrection(): void
    {
        $this->authorize('correctStatus', $this->workOrder);
        $this->resetErrorBag();
        $this->correction = [
            'to' => '',
            'reason' => '',
            'completed_at' => ($this->workOrder->completed_at ?? now())->format('Y-m-d\TH:i'),
            'waiting_reason' => $this->workOrder->waiting_reason->value ?? 'ricambi',
            'downtime' => '',
        ];
        $this->correcting = true;
    }

    public function saveCorrection(CorrectWorkOrderStatus $correct): void
    {
        $this->authorize('correctStatus', $this->workOrder);
        $input = $this->correction;
        $input['downtime'] = $input['downtime'] ?: null;
        $input['completed_at'] = $input['completed_at'] ?: null;

        try {
            $correct->handle($this->workOrder, auth()->user(), $input);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('correction.'.$field, $messages[0]);
            }

            return;
        }

        $this->correcting = false;
        $this->workOrder->refresh();
        $this->dispatch('toast', message: __('maintenance.correction.done', ['status' => $this->workOrder->status->label()]));
    }

    // ---------- Assegnatari e dati ----------

    public function editAssignees(): void
    {
        $this->authorize('assign', $this->workOrder);
        $this->assignees = $this->workOrder->assignees()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
        $this->editingAssignees = true;
    }

    public function saveAssignees(AssignWorkOrder $assign): void
    {
        $this->authorize('assign', $this->workOrder);
        try {
            $assign->handle($this->workOrder, array_map('intval', $this->assignees), auth()->user());
        } catch (ValidationException $e) {
            $this->addError('assignees', (string) collect($e->errors())->flatten()->first());

            return;
        }
        $this->editingAssignees = false;
        $this->workOrder->refresh();
    }

    public function editDetails(): void
    {
        $this->authorize('update', $this->workOrder);
        $o = $this->workOrder;
        $this->details = [
            'title' => $o->title,
            'description' => (string) $o->description,
            'priority' => $o->priority->value,
            'due_at' => $o->due_at?->toDateString() ?? '',
            'planned_start_at' => $o->planned_start_at?->format('Y-m-d\TH:i') ?? '',
        ];
        $this->editing = true;
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->workOrder);
        $this->validate([
            'details.title' => ['required', 'string', 'max:160'],
            'details.description' => ['nullable', 'string', 'max:5000'],
            'details.priority' => ['required', Rule::enum(Priority::class)],
            'details.due_at' => ['nullable', 'date'],
            'details.planned_start_at' => ['nullable', 'date'],
        ], [], ['details.title' => __('maintenance.field.title')]);

        $this->workOrder->fill([
            'title' => trim($this->details['title']),
            'description' => trim($this->details['description']) ?: null,
            'priority' => $this->details['priority'],
            'due_at' => $this->details['due_at'] ?: null,
            'planned_start_at' => $this->details['planned_start_at'] ?: null,
        ])->save();
        Audit::log('work_orders.updated', $this->workOrder, $this->workOrder->number, Audit::diff($this->workOrder));
        $this->editing = false;
    }

    // ---------- Checklist ----------

    public function toggleItem(int $itemId): void
    {
        $this->authorize('execute', $this->workOrder);
        $item = $this->item($itemId);
        if ($item->kind === 'valore' && ! $item->isDone()) {
            $this->saveValue($itemId);

            return;
        }
        $item->forceFill($item->isDone()
            ? ['done_at' => null, 'done_by' => null, 'value' => null]
            : ['done_at' => now(), 'done_by' => auth()->id()])->save();
    }

    public function saveValue(int $itemId): void
    {
        $this->authorize('execute', $this->workOrder);
        $item = $this->item($itemId);
        $this->resetErrorBag('values.'.$itemId);
        $value = trim($this->values[$itemId] ?? '');
        if ($value === '') {
            $this->addError('values.'.$itemId, __('maintenance.checklist.value_required'));

            return;
        }
        $item->forceFill(['value' => mb_strimwidth($value, 0, 60, ''), 'done_at' => now(), 'done_by' => auth()->id()])->save();
        unset($this->values[$itemId]);
    }

    public function addItem(): void
    {
        $this->authorize('execute', $this->workOrder);
        $this->validate(['newItem' => ['required', 'string', 'max:255']], [], ['newItem' => __('maintenance.checklist.item')]);
        $this->workOrder->checklist()->create([
            'position' => (int) $this->workOrder->checklist()->max('position') + 1,
            'label' => trim($this->newItem),
            'kind' => 'spunta',
            'required' => false,
        ]);
        $this->newItem = '';
    }

    public function applyTemplate(CreateWorkOrder $create): void
    {
        $this->authorize('execute', $this->workOrder);
        if (! ctype_digit($this->template)) {
            return;
        }
        $create->copyChecklist($this->workOrder, (int) $this->template);
        $this->template = '';
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('assign', $this->workOrder);
        $this->item($itemId)->delete();
    }

    private function item(int $id): WorkOrderChecklistItem
    {
        return $this->workOrder->checklist()->whereKey($id)->firstOrFail();
    }

    // ---------- Tempi ----------

    public function startTimer(WorkTimer $timer): void
    {
        $this->authorize('execute', $this->workOrder);
        abort_unless(in_array($this->workOrder->status, [WorkOrderStatus::InProgress], true), 422);
        $timer->start($this->workOrder, auth()->user());
    }

    public function stopTimer(WorkTimer $timer): void
    {
        $timer->stop($this->workOrder, auth()->user());
    }

    public function addTime(WorkTimer $timer): void
    {
        $this->authorize('execute', $this->workOrder);
        try {
            $timer->add($this->workOrder, auth()->user(), $this->manualTime);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('manualTime.'.$field, $messages[0]);
            }

            return;
        }
        $this->manualTime = ['minutes' => '', 'date' => now()->toDateString(), 'note' => ''];
        $this->addingTime = false;
    }

    public function deleteTime(int $entryId): void
    {
        $entry = $this->workOrder->timeEntries()->whereKey($entryId)->firstOrFail();
        abort_unless($entry->user_id === auth()->id() || auth()->user()->can('work_orders.assign'), 403);
        $entry->delete();
    }

    // ---------- Fermo macchina ----------

    public function saveDowntime(int $eventId): void
    {
        $this->authorize('execute', $this->workOrder);
        $event = DowntimeEvent::query()->where('asset_id', $this->workOrder->asset_id)->whereKey($eventId)->firstOrFail();
        $data = $this->downtime[$eventId] ?? [];
        $this->validate([
            "downtime.$eventId.start" => ['required', 'date', 'before_or_equal:now'],
            "downtime.$eventId.end" => ['nullable', 'date', "after:downtime.$eventId.start", 'before_or_equal:now'],
        ], [], ["downtime.$eventId.start" => __('maintenance.downtime.start'), "downtime.$eventId.end" => __('maintenance.downtime.end')]);
        $event->forceFill([
            'started_at' => Carbon::parse($data['start']),
            'ended_at' => ! empty($data['end']) ? Carbon::parse($data['end']) : null,
            'work_order_id' => $event->work_order_id ?? $this->workOrder->id,
        ])->save();
        unset($this->downtime[$eventId]);
    }

    public function startDowntime(): void
    {
        $this->authorize('execute', $this->workOrder);
        abort_unless($this->workOrder->asset_id !== null, 422);
        if (! DowntimeEvent::query()->where('asset_id', $this->workOrder->asset_id)->open()->exists()) {
            DowntimeEvent::query()->create(['asset_id' => $this->workOrder->asset_id, 'work_order_id' => $this->workOrder->id, 'started_at' => now()]);
        }
    }

    // ---------- Foto e commenti ----------

    public function updatedPhotos(StoreAttachment $store): void
    {
        $this->authorize('execute', $this->workOrder);
        foreach ($this->photos as $file) {
            try {
                $saved = $store->handle($this->workOrder, $file, AttachmentKind::Photo);
                if ($saved->notice) {
                    $this->addError('photos', $file->getClientOriginalName().': '.$saved->notice);
                }
            } catch (RuntimeException $e) {
                $this->addError('photos', $file->getClientOriginalName().': '.$e->getMessage());
            } finally {
                $file->delete();
            }
        }
        $this->photos = [];
    }

    public function deletePhoto(int $id): void
    {
        $attachment = $this->workOrder->attachments()->whereKey($id)->firstOrFail();
        abort_unless($attachment->user_id === auth()->id() || auth()->user()->can('work_orders.assign'), 403);
        $attachment->delete();
    }

    public function addComment(): void
    {
        $this->authorize('comment', $this->workOrder);
        $this->validate(['comment' => ['required', 'string', 'max:2000']], [], ['comment' => __('maintenance.comments.label')]);
        $this->workOrder->comments()->create(['user_id' => auth()->id(), 'body' => trim($this->comment)]);
        $this->comment = '';
    }

    // ---------- Costi ----------

    public function saveExternalCost(): void
    {
        $this->authorize('update', $this->workOrder);
        $this->authorize('viewCosts', $this->workOrder);
        $value = $this->externalCost === '' ? null : Numbers::parse($this->externalCost);
        if ($this->externalCost !== '' && ($value === null || $value < 0)) {
            $this->addError('externalCost', __('assets.meters.invalid'));

            return;
        }
        $this->workOrder->forceFill(['external_cost' => $value])->save();
        $this->externalCost = '';
    }

    public function render(): View
    {
        $o = $this->workOrder->load([
            'asset.location', 'location', 'assignees', 'creator', 'request.user', 'request.attachments',
            'checklist.doneBy', 'timeEntries.user', 'attachments.user', 'comments.user', 'statusChanges.user',
            'failureProblem', 'failureCause', 'failureRemedy',
        ]);
        $user = auth()->user();

        // Storico: commenti e cambi di stato insieme, in ordine di tempo
        $timeline = collect()
            ->concat($o->comments->map(fn ($c) => ['kind' => 'comment', 'at' => $c->created_at, 'item' => $c]))
            ->concat($o->statusChanges->map(fn ($s) => ['kind' => 'status', 'at' => $s->created_at, 'item' => $s]))
            ->sortBy(fn ($e) => $e['at']->getTimestamp())->values();

        $transitions = collect($o->status->transitions())
            ->keys()->map(fn ($v) => WorkOrderStatus::from($v))
            ->filter(fn (WorkOrderStatus $to) => $user->can('transition', [$o, $to]))
            // "Pianificato" si ottiene assegnando: non serve un pulsante a parte
            ->reject(fn (WorkOrderStatus $to) => $to === WorkOrderStatus::Planned)
            ->values();

        $downtimes = $o->asset_id
            ? DowntimeEvent::query()->where('asset_id', $o->asset_id)
                ->where(fn ($q) => $q->where('work_order_id', $o->id)->orWhereNull('ended_at')
                    ->orWhere('work_request_id', $o->request->id ?? 0))
                ->orderBy('started_at')->get()
            : collect();

        /** @var Collection<int, TimeEntry> $entries */
        $entries = $o->timeEntries;

        return view('livewire.work-orders.show', [
            'o' => $o,
            'timeline' => $timeline,
            'transitions' => $transitions,
            'can' => [
                'execute' => $user->can('execute', $o),
                'take' => $user->can('take', $o),
                'assign' => $user->can('assign', $o),
                'update' => $user->can('update', $o),
                'comment' => $user->can('comment', $o),
                'costs' => $user->can('viewCosts', $o),
                'correct' => $user->can('correctStatus', $o),
            ],
            'linkedDowntimes' => $this->correcting ? app(CorrectWorkOrderStatus::class)->linkedDowntimes($o) : collect(),
            'allStatuses' => WorkOrderStatus::cases(),
            'myTimer' => $entries->first(fn (TimeEntry $t) => $t->isRunning() && $t->user_id === $user->id),
            'running' => $entries->filter(fn (TimeEntry $t) => $t->isRunning()),
            'laborMinutes' => $o->laborMinutes(),
            'costs' => $o->costs(),
            'downtimes' => $downtimes,
            'gallery' => $o->attachments,
            'requestPhotos' => $o->request !== null ? $o->request->attachments : collect(),
            'executors' => $this->editingAssignees ? User::permission('work_orders.execute')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']) : collect(),
            'templates' => ChecklistTemplate::query()->orderBy('name')->pluck('name', 'id'),
            'codes' => FailureCode::query()->active()->orderBy('code')->get()->groupBy(fn (FailureCode $c) => $c->kind->value),
            'kinds' => FailureCodeKind::cases(),
            'reasons' => WaitingReason::cases(),
            'priorities' => Priority::cases(),
        ])->title($o->number.' · '.$o->title)->layoutData(['heading' => $o->number]);
    }
}
