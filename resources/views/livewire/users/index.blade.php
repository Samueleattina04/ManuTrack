<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-end gap-3">
        <div class="field min-w-[14rem] flex-1">
            <label for="u-search" class="field-label">{{ __('users.search') }}</label>
            <input id="u-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('users.search_placeholder') }}">
        </div>
        <div class="field">
            <label for="u-role" class="field-label">{{ __('users.field.role') }}</label>
            <select id="u-role" wire:model.live="role" class="input">
                <option value="">{{ __('users.all_roles') }}</option>
                @foreach($roles as $r)<option value="{{ $r }}">{{ __('access.roles.'.$r) }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="u-status" class="field-label">{{ __('users.field.status') }}</label>
            <select id="u-status" wire:model.live="status" class="input">
                <option value="active">{{ __('users.status.active') }}</option>
                <option value="inactive">{{ __('users.status.inactive') }}</option>
                <option value="all">{{ __('users.status.all') }}</option>
            </select>
        </div>
        @if($canManage)
            <button type="button" class="btn btn-primary" wire:click="$set('inviting', true)"><x-icon name="plus" />{{ __('users.invite.button') }}</button>
        @endif
    </div>

    @if($inviting)
        <form wire:submit="invite" class="card flex flex-col gap-4 p-5" aria-labelledby="invite-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="invite-title" class="text-lg font-bold">{{ __('users.invite.title') }}</h2>
                <button type="button" class="btn btn-ghost btn-icon" wire:click="$set('inviting', false)" aria-label="{{ __('app.close') }}"><x-icon name="x" /></button>
            </div>
            <p class="text-sm text-fg-2">{{ __('users.invite.intro', ['days' => \App\Models\Invitation::VALID_DAYS]) }}</p>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="field">
                    <label for="inv-email" class="field-label">{{ __('users.field.email') }}</label>
                    <input id="inv-email" type="email" inputmode="email" wire:model="inviteEmail" class="input" @error('inviteEmail') aria-invalid="true" aria-describedby="inv-email-err" @enderror required>
                    @error('inviteEmail')<span id="inv-email-err" class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="inv-name" class="field-label">{{ __('users.field.name_optional') }}</label>
                    <input id="inv-name" type="text" wire:model="inviteName" class="input" autocomplete="off">
                </div>
                <div class="field">
                    <label for="inv-role" class="field-label">{{ __('users.field.role') }}</label>
                    <select id="inv-role" wire:model="inviteRole" class="input">
                        @foreach($roles as $r)<option value="{{ $r }}">{{ __('access.roles.'.$r) }}</option>@endforeach
                    </select>
                </div>
            </div>
            <p class="text-sm text-muted">{{ __('access.descriptions.'.$inviteRole) }}</p>
            <div><button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-icon name="arrow-right" />{{ __('users.invite.send') }}</button></div>
        </form>
    @endif

    @if($invitations->isNotEmpty())
        <section class="card overflow-hidden" aria-labelledby="pending-title">
            <h2 id="pending-title" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('users.invite.pending', ['count' => $invitations->count()]) }}</h2>
            <ul>
                @foreach($invitations as $inv)
                    <li class="flex flex-wrap items-center gap-3 border-b border-line-soft px-4 py-2.5 last:border-0" wire:key="inv-{{ $inv->id }}">
                        <x-icon name="clock" class="text-warn" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ $inv->name ? $inv->name.' · ' : '' }}{{ $inv->email }}</div>
                            <div class="text-xs text-muted">{{ __('access.roles.'.$inv->role) }} · {{ __('users.invite.expires', ['date' => $inv->expires_at->translatedFormat('j M Y')]) }}</div>
                        </div>
                        @if($canManage)
                            <div class="flex w-full justify-end gap-1 sm:w-auto">
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="resend({{ $inv->id }})">{{ __('users.invite.resend') }}</button>
                                <button type="button" class="btn btn-ghost btn-sm text-ko" wire:click="revoke({{ $inv->id }})" wire:confirm="{{ __('users.invite.revoke_confirm') }}">{{ __('users.invite.revoke') }}</button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card overflow-hidden" aria-label="{{ __('users.title') }}">
        {{-- PC: tabella densa --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <thead><tr>
                    <th>{{ __('users.field.name') }}</th><th>{{ __('users.field.role') }}</th><th>{{ __('users.field.status') }}</th>
                    <th>{{ __('users.field.two_factor') }}</th><th>{{ __('users.field.last_login') }}</th><th class="text-right">{{ __('users.actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse($users as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-surface-2 text-xs font-bold text-fg-2" aria-hidden="true">{{ $u->initials() }}</span>
                                <div class="min-w-0"><div class="font-medium">{{ $u->name }}</div><div class="text-xs text-muted">{{ $u->email }}{{ $u->job_title ? ' · '.$u->job_title : '' }}</div></div>
                            </div>
                        </td>
                        <td>
                            @if($canManage && ! $u->anonymized_at)
                                <label class="sr-only" for="role-{{ $u->id }}">{{ __('users.field.role') }} {{ $u->name }}</label>
                                <select id="role-{{ $u->id }}" class="input !min-h-[36px] !py-0 text-sm" wire:change="changeRole({{ $u->id }}, $event.target.value)">
                                    @foreach($roles as $r)<option value="{{ $r }}" @selected($u->roleName() === $r)>{{ __('access.roles.'.$r) }}</option>@endforeach
                                </select>
                            @else
                                {{ $u->roleName() ? __('access.roles.'.$u->roleName()) : '—' }}
                            @endif
                        </td>
                        <td>@if($u->is_active)<x-status tone="ok" icon="check-circle">{{ __('users.status.active_one') }}</x-status>@else<x-status tone="stop" icon="pause-circle">{{ __('users.status.inactive_one') }}</x-status>@endif</td>
                        <td>@if($u->hasTwoFactor())<x-status tone="ok" icon="shield">{{ __('users.two_factor_on') }}</x-status>@else<span class="text-muted">—</span>@endif</td>
                        <td class="num text-fg-2">{{ $u->last_login_at?->diffForHumans() ?? __('users.never') }}</td>
                        <td class="text-right">
                            @if($u->anonymized_at)
                                <span class="text-sm text-muted">{{ __('users.anonymized') }}</span>
                            @elseif($canManage)
                                {{-- Menu spostato nel body: la tabella scorre in orizzontale e taglierebbe un menu "absolute" --}}
                                <div x-data="{ open: false, pos: '' }" class="relative inline-block text-left" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                                    <button type="button" x-ref="btn" class="btn btn-ghost btn-sm" :aria-expanded="open.toString()"
                                        @click="const r = $refs.btn.getBoundingClientRect(); const up = r.bottom + 240 > window.innerHeight; pos = 'right:' + (window.innerWidth - r.right) + 'px;' + (up ? 'bottom:' + (window.innerHeight - r.top + 4) : 'top:' + (r.bottom + 4)) + 'px'; open = ! open">{{ __('users.more') }} <x-icon name="chevron-down" class="!w-4 !h-4" /></button>
                                    <template x-teleport="body">
                                    <div x-cloak x-show="open" x-transition :style="pos" class="fixed z-50 w-60 card p-1.5 text-left" @click.outside="if (! $refs.btn.contains($event.target)) open = false">
                                        <button type="button" class="nav-item w-full" wire:click="sendPasswordReset({{ $u->id }})" @click="open = false"><x-icon name="key" />{{ __('users.send_reset') }}</button>
                                        @if($u->hasTwoFactor())
                                            <button type="button" class="nav-item w-full" wire:click="resetTwoFactor({{ $u->id }})" wire:confirm="{{ __('users.reset_2fa_confirm') }}" @click="open = false"><x-icon name="shield" />{{ __('users.reset_2fa') }}</button>
                                        @endif
                                        <button type="button" class="nav-item w-full" wire:click="toggleActive({{ $u->id }})" @if($u->is_active) wire:confirm="{{ __('users.deactivate_confirm', ['name' => $u->name]) }}" @endif @click="open = false">
                                            <x-icon :name="$u->is_active ? 'pause-circle' : 'check-circle'" />{{ $u->is_active ? __('users.deactivate') : __('users.activate') }}
                                        </button>
                                        @if(! $u->is_active && $isAdmin && ! $u->is(auth()->user()))
                                            <a href="{{ route('gdpr.anonymize', ['persona' => $u->id]) }}" class="nav-item w-full" wire:navigate><x-icon name="shield" />{{ __('users.anonymize') }}</a>
                                        @endif
                                    </div>
                                    </template>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">{{ __('users.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Telefono: schede --}}
        <ul class="md:hidden">
            @forelse($users as $u)
                <li class="flex items-center gap-3 border-b border-line-soft px-4 py-3 last:border-0" wire:key="um-{{ $u->id }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-surface-2 text-xs font-bold text-fg-2" aria-hidden="true">{{ $u->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $u->name }}</div>
                        <div class="truncate text-xs text-muted">{{ $u->roleName() ? __('access.roles.'.$u->roleName()) : '—' }} · {{ $u->email }}</div>
                    </div>
                    @if(! $u->is_active)<x-status tone="stop" icon="pause-circle">{{ __('users.status.inactive_one') }}</x-status>@endif
                </li>
            @empty
                <li class="px-4 py-8 text-center text-muted">{{ __('users.empty') }}</li>
            @endforelse
        </ul>
    </section>

    {{ $users->links() }}
</div>
