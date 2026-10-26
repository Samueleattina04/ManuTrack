<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-end gap-3">
        <div class="field min-w-[14rem] flex-1">
            <label for="a-search" class="field-label">{{ __('users.search') }}</label>
            <input id="a-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('audit.search_placeholder') }}">
        </div>
        <div class="field">
            <label for="a-group" class="field-label">{{ __('audit.area') }}</label>
            <select id="a-group" wire:model.live="group" class="input">
                <option value="">{{ __('audit.all') }}</option>
                @foreach($groups as $g)<option value="{{ $g }}">{{ __('audit.groups.'.$g) }}</option>@endforeach
            </select>
        </div>
    </div>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table min-w-[760px]">
                <thead><tr><th>{{ __('audit.when') }}</th><th>{{ __('audit.who') }}</th><th>{{ __('audit.what') }}</th><th>{{ __('audit.detail') }}</th><th>{{ __('audit.from') }}</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr wire:key="a-{{ $log->id }}">
                        <td class="num whitespace-nowrap text-fg-2" title="{{ $log->created_at->toDateTimeString() }}">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap">{{ $log->user_name ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Facades\Lang::has('audit.actions.'.$log->action) ? __('audit.actions.'.$log->action) : $log->action }}</td>
                        <td class="max-w-[22rem]">
                            <span class="text-fg-2">{{ $log->description }}</span>
                            @if($log->changes)
                                <details class="mt-1 text-xs"><summary class="cursor-pointer text-muted">{{ __('audit.changes') }}</summary>
                                    <ul class="mt-1 flex flex-col gap-0.5">@foreach($log->changes as $field => $pair)<li><span class="font-mono">{{ $field }}</span>: @if(is_array($pair) && array_is_list($pair) && count($pair) === 2)<span class="text-muted">{{ json_encode($pair[0], JSON_UNESCAPED_UNICODE) }}</span> → {{ json_encode($pair[1], JSON_UNESCAPED_UNICODE) }}@else{{ json_encode($pair, JSON_UNESCAPED_UNICODE) }}@endif</li>@endforeach</ul>
                                </details>
                            @endif
                        </td>
                        <td class="code whitespace-nowrap">{{ $log->ip ?? 'console' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-muted">{{ __('audit.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $logs->links() }}
</div>
