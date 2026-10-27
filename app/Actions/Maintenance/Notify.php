<?php

namespace App\Actions\Maintenance;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use App\Support\Push\PushUrgency;
use Illuminate\Support\Collection;

/**
 * Avvisi push di segnalazioni e ordini di lavoro.
 * Le urgenti partono subito dopo la risposta, le altre passano dalla coda.
 * Chi ha fatto l'azione non riceve mai l'avviso.
 */
class Notify
{
    public function __construct(private PushDispatcher $push) {}

    public function newRequest(WorkRequest $request, User $actor): void
    {
        $urgent = $request->isUrgent();
        $what = $request->asset ? $request->asset->code.' '.$request->asset->name : ($request->location?->fullName() ?? __('maintenance.request.no_asset'));

        $this->push->send(
            $this->without(self::triagers(), $actor),
            new PushMessage(
                title: __($urgent ? 'maintenance.push.request_urgent' : 'maintenance.push.request_new', ['what' => $what]),
                body: collect([
                    $request->machine_stopped ? __('maintenance.push.machine_stopped') : null,
                    $request->summary(120),
                    __('assets.push.by', ['name' => $actor->name]),
                ])->filter()->implode(' · '),
                url: route('requests.show', $request, absolute: false),
                tag: 'request-'.$request->id,
            ),
            $urgent ? PushUrgency::Immediate : PushUrgency::Normal,
        );
    }

    public function requestTriaged(WorkRequest $request, User $actor): void
    {
        if (! $request->user || $request->user_id === $actor->id) {
            return;
        }

        $this->push->send($request->user, new PushMessage(
            title: __('maintenance.push.request_'.$request->status->value, ['number' => $request->number]),
            body: $request->triage_note ?: $request->summary(120),
            url: route('requests.show', $request, absolute: false),
            tag: 'request-'.$request->id,
        ));
    }

    /**
     * @param  Collection<int, User>  $users  persone appena aggiunte all'ordine
     */
    public function assigned(WorkOrder $order, Collection $users, ?User $actor): void
    {
        $critical = $order->priority->value === 'critica';
        $this->push->send($this->without($users, $actor), new PushMessage(
            title: __($critical ? 'maintenance.push.assigned_critical' : 'maintenance.push.assigned', ['number' => $order->number]),
            body: trim(($order->asset ? $order->asset->code.' · ' : '').$order->title),
            url: route('work-orders.show', $order, absolute: false),
            tag: 'wo-'.$order->id,
        ), $critical ? PushUrgency::Immediate : PushUrgency::Normal);
    }

    public function completed(WorkOrder $order, User $actor): void
    {
        $author = $order->request?->user;
        if ($author && $author->id !== $actor->id) {
            $this->push->send($author, new PushMessage(
                title: __('maintenance.push.completed', ['number' => $order->request->number]),
                body: trim(($order->asset ? $order->asset->code.' · ' : '').$order->title),
                url: route('requests.show', $order->request, absolute: false),
                tag: 'request-'.$order->request->id,
            ));
        }
    }

    /**
     * @return Collection<int, User>
     */
    public static function triagers(): Collection
    {
        return User::permission('requests.triage')->where('is_active', true)->get();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function without(Collection $users, ?User $actor): Collection
    {
        return $users->reject(fn (User $u) => $u->id === $actor?->id)->values();
    }
}
