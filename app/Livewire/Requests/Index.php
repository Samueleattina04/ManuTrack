<?php

namespace App\Livewire\Requests;

use App\Enums\RequestStatus;
use App\Models\WorkRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Segnalazioni: chi valuta vede per prime quelle da valutare (le urgenti in cima);
 * chi segnala vede le proprie.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'stato')]
    public string $status = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('requests.create') || $user->can('requests.view_all'), 403);
        if ($this->status === '' && $user->can('requests.triage')) {
            $this->status = RequestStatus::New->value;
        }
    }

    public function updating(string $field): void
    {
        if (in_array($field, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $base = WorkRequest::query()->visibleTo($user)
            ->when(trim($this->search) !== '', function ($q) {
                $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('description', 'like', $like)
                    ->orWhereHas('asset', fn ($a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
            });

        $counts = (clone $base)->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);

        $requests = (clone $base)
            ->with(['asset', 'location', 'user', 'workOrder'])
            ->withCount('attachments')
            ->when(RequestStatus::tryFrom($this->status), fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("case status when 'nuova' then 0 else 1 end")
            ->orderByRaw("case priority when 'critica' then 0 when 'alta' then 1 else 2 end")
            ->orderByDesc('machine_stopped')
            ->latest('reported_at')
            ->paginate(25);

        return view('livewire.requests.index', [
            'requests' => $requests,
            'counts' => $counts,
            'statuses' => RequestStatus::cases(),
            'mineOnly' => ! $user->can('requests.view_all'),
        ])->title(__('maintenance.request.title'))->layoutData(['heading' => __('maintenance.request.title')]);
    }
}
