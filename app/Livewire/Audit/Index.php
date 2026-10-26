<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registro delle attività: chi ha fatto cosa, quando e da dove.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $group = '';

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updating(string $field): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->when($this->group !== '', fn ($q) => $q->where('action', 'like', $this->group.'.%'))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('user_name', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%')
                ->orWhere('ip', 'like', $this->search.'%')))
            ->latest('id')
            ->paginate(50);

        return view('livewire.audit.index', [
            'logs' => $logs,
            'groups' => ['auth', 'users', 'roles', 'settings', 'system', 'profile'],
        ])->title(__('audit.title'))->layoutData(['heading' => __('audit.title')]);
    }
}
