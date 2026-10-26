<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="max-w-prose text-fg-2">{{ __('access.intro') }}</p>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($roles as $r)
            <div class="card p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-display text-base font-bold">{{ __('access.roles.'.$r) }}</h2>
                    @if($r !== \App\Support\Access\Access::ADMIN)
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="restoreDefaults('{{ $r }}')" wire:confirm="{{ __('access.restore_confirm') }}">{{ __('access.restore') }}</button>
                    @endif
                </div>
                <p class="mt-1 text-sm text-fg-2">{{ __('access.descriptions.'.$r) }}</p>
            </div>
        @endforeach
    </div>

    <section class="card overflow-hidden" aria-label="{{ __('access.matrix') }}">
        <div class="overflow-x-auto">
            <table class="table min-w-[760px]">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-surface">{{ __('access.permission') }}</th>
                        @foreach($roles as $r)<th class="text-center">{{ __('access.roles.'.$r) }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($groups as $group => $permissions)
                    <tr><td colspan="{{ count($roles) + 1 }}" class="bg-bg-2 font-mono text-[11px] uppercase tracking-wider text-muted">{{ __('access.groups.'.$group) }}</td></tr>
                    @foreach($permissions as $perm)
                        <tr wire:key="p-{{ $perm }}">
                            <td class="sticky left-0 z-10 bg-surface">{{ __('access.permissions.'.$perm) }}</td>
                            @foreach($roles as $r)
                                @php($on = $r === \App\Support\Access\Access::ADMIN || in_array($perm, $granted[$r] ?? [], true))
                                <td class="text-center">
                                    @if($r === \App\Support\Access\Access::ADMIN)
                                        <x-icon name="check" class="mx-auto text-ok" :label="__('access.always')" />
                                    @else
                                        <label class="inline-grid h-11 w-11 cursor-pointer place-items-center">
                                            <input type="checkbox" class="h-5 w-5 accent-[var(--mt-accent)]" @checked($on)
                                                   wire:click="toggle('{{ $r }}', '{{ $perm }}')"
                                                   aria-label="{{ __('access.permissions.'.$perm) }} · {{ __('access.roles.'.$r) }}">
                                        </label>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
