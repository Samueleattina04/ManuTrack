<form wire:submit="save" class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="max-w-prose text-sm text-fg-2">{{ __('notifications.rules.intro') }}</p>
    <section class="card overflow-hidden" aria-label="{{ __('notifications.rules.title') }}">
        <div class="overflow-x-auto">
            <table class="table min-w-[900px]">
                <thead><tr>
                    <th>{{ __('notifications.rules.event') }}</th>
                    @foreach($roles as $role)<th>{{ __('access.roles.'.$role) }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach($events as $e)
                        <tr wire:key="r-{{ $e->value }}">
                            <td><span class="font-semibold">{{ $e->label() }}</span><span class="block text-xs text-muted">{{ $e->description() }}</span></td>
                            @foreach($roles as $role)
                                <td>
                                    <label class="sr-only" for="r-{{ $role }}-{{ $e->value }}">{{ __('access.roles.'.$role) }} · {{ $e->label() }}</label>
                                    <select id="r-{{ $role }}-{{ $e->value }}" wire:model="rules.{{ $role }}.{{ $e->value }}" class="input h-10 w-auto text-sm">
                                        @foreach($modes as $m)<option value="{{ $m }}">{{ __('notifications.email_modes.'.$m) }}</option>@endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <p class="text-xs text-muted">{{ __('notifications.rules.note') }}</p>
    <button type="submit" class="btn btn-primary btn-xl self-start"><x-icon name="check" />{{ __('app.save') }}</button>
</form>
