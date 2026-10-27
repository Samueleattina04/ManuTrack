<?php

namespace App\Livewire\Notifications;

use App\Models\UserNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;
use Livewire\WithPagination;

/**
 * Centro avvisi: gli avvisi della persona, raggruppati per giorno.
 * Avvisi consecutivi sulla stessa cosa (stesso tag) si mostrano come uno solo.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'da_leggere')]
    public bool $unreadOnly = false;

    public function paginationView(): string
    {
        return 'components.pagination';
    }

    public function open(int $id): RedirectResponse|Redirector
    {
        $n = UserNotification::query()->where('user_id', auth()->id())->findOrFail($id);
        // Letti insieme tutti gli avvisi sulla stessa cosa
        UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')
            ->when($n->tag, fn ($q) => $q->where('tag', $n->tag), fn ($q) => $q->whereKey($n->id))
            ->update(['read_at' => now()]);

        return redirect($n->url && str_starts_with($n->url, '/') ? $n->url : route('notifications.index'));
    }

    public function markAllRead(): void
    {
        UserNotification::query()->where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        $this->dispatch('toast', message: __('notifications.all_read_done'));
    }

    public function render(): View
    {
        $page = UserNotification::query()->where('user_id', auth()->id())
            ->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->latest('id')->paginate(40);

        // Raggruppamento: avvisi di fila con lo stesso tag diventano una riga "× N"
        $rows = [];
        foreach ($page->items() as $n) {
            $last = array_key_last($rows);
            if ($last !== null && $n->tag && $rows[$last]['item']->tag === $n->tag && $rows[$last]['item']->created_at->isSameDay($n->created_at)) {
                $rows[$last]['count']++;
                $rows[$last]['unread'] = $rows[$last]['unread'] || $n->read_at === null;

                continue;
            }
            $rows[] = ['item' => $n, 'count' => 1, 'unread' => $n->read_at === null];
        }
        $days = collect($rows)->groupBy(fn ($r) => $r['item']->created_at->toDateString());

        return view('livewire.notifications.index', [
            'days' => $days,
            'page' => $page,
            'unread' => UserNotification::unreadCount(auth()->user()),
        ])->title(__('notifications.title'))->layoutData(['heading' => __('notifications.title')]);
    }
}
