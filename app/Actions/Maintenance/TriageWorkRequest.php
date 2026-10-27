<?php

namespace App\Actions\Maintenance;

use App\Enums\RequestStatus;
use App\Enums\WorkOrderType;
use App\Models\DowntimeEvent;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Valutazione di una segnalazione: accettata (nasce l'ordine di lavoro),
 * respinta (con il motivo) o duplicata di un'altra.
 * Chi l'ha fatta riceve un avviso.
 */
class TriageWorkRequest
{
    public function __construct(private CreateWorkOrder $create, private Notify $notify) {}

    /**
     * @param  array<string, mixed>  $input  priority, type, title, assignees, due_at, checklist_template_id, note
     */
    public function accept(WorkRequest $request, User $actor, array $input = []): WorkOrder
    {
        $this->ensureNew($request);
        $request->loadMissing('asset');

        $order = DB::transaction(function () use ($request, $actor, $input) {
            $order = $this->create->handle($actor, [
                'type' => $input['type'] ?? WorkOrderType::Corrective->value,
                'title' => $input['title'] ?? trim(($request->asset ? $request->asset->code.' · ' : '').$request->summary(120)),
                'description' => $request->description,
                'asset_id' => $request->asset_id,
                'location_id' => $request->location_id,
                'priority' => $input['priority'] ?? $request->priority->value,
                'due_at' => $input['due_at'] ?? null,
                'assignees' => $input['assignees'] ?? [],
                'checklist_template_id' => $input['checklist_template_id'] ?? null,
            ]);

            $request->forceFill([
                'status' => RequestStatus::Accepted,
                'work_order_id' => $order->id,
                'triaged_by' => $actor->id,
                'triaged_at' => now(),
                'triage_note' => isset($input['note']) ? trim((string) $input['note']) ?: null : null,
            ])->save();

            // Il fermo aperto dalla segnalazione passa all'ordine
            DowntimeEvent::query()->where('work_request_id', $request->id)->whereNull('work_order_id')->update(['work_order_id' => $order->id]);

            return $order;
        });

        Audit::log('requests.accepted', $request, $request->number.' → '.$order->number, actor: $actor);
        $this->notify->requestTriaged($request, $actor);

        return $order;
    }

    public function reject(WorkRequest $request, User $actor, string $note): void
    {
        $this->ensureNew($request);
        $note = trim($note);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'min:3', 'max:500']], [], ['note' => __('maintenance.field.reject_note')])->validate();

        $request->forceFill([
            'status' => RequestStatus::Rejected,
            'triage_note' => $note,
            'triaged_by' => $actor->id,
            'triaged_at' => now(),
        ])->save();

        Audit::log('requests.rejected', $request, $request->number.' · '.$note, actor: $actor);
        $this->notify->requestTriaged($request, $actor);
    }

    public function duplicate(WorkRequest $request, User $actor, int $originalId, ?string $note = null): void
    {
        $this->ensureNew($request);
        Validator::make(['original' => $originalId], [
            'original' => ['required', 'integer', Rule::exists('work_requests', 'id'), Rule::notIn([$request->id])],
        ], [], ['original' => __('maintenance.request.original')])->validate();

        $original = WorkRequest::query()->findOrFail($originalId);
        $request->forceFill([
            'status' => RequestStatus::Duplicate,
            'duplicate_of_id' => $original->id,
            'work_order_id' => $original->work_order_id,
            'triage_note' => $note ? trim($note) : __('maintenance.request.duplicate_of', ['number' => $original->number]),
            'triaged_by' => $actor->id,
            'triaged_at' => now(),
        ])->save();

        Audit::log('requests.duplicate', $request, $request->number.' = '.$original->number, actor: $actor);
        $this->notify->requestTriaged($request, $actor);
    }

    private function ensureNew(WorkRequest $request): void
    {
        if ($request->status !== RequestStatus::New) {
            throw ValidationException::withMessages(['status' => __('maintenance.errors.already_triaged', ['status' => $request->status->label()])]);
        }
    }
}
