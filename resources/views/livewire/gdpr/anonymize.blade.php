<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <x-gdpr-tabs current="anonymize" />

    @if($result)
        <div class="alert alert-ok" role="status"><x-icon name="check-circle" /><span>{{ __('gdpr.anonymize.done', ['name' => $result['pseudonym'], 'n' => array_sum($result['replaced'])]) }}</span></div>
    @endif

    <section class="card flex flex-col gap-4 p-4 sm:p-5" aria-labelledby="a-title">
        <h2 id="a-title" class="text-base font-bold">{{ __('gdpr.anonymize.title') }}</h2>
        <p class="max-w-prose text-fg-2">{{ __('gdpr.anonymize.intro') }}</p>
        <form wire:submit="anonymize" class="flex flex-col gap-4">
            <div class="field">
                <label for="a-user" class="field-label">{{ __('gdpr.anonymize.person') }}</label>
                <select id="a-user" wire:model.live="userId" class="input">
                    <option value="">{{ __('gdpr.anonymize.no_account') }}</option>
                    @foreach($people as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} · {{ $p->email }}{{ $p->is_active ? ' · '.__('gdpr.anonymize.active') : '' }}</option>
                    @endforeach
                </select>
                @error('user')<span class="field-error"><x-icon name="alert-circle" class="!h-4 !w-4" />{{ $message }}</span>@enderror
                @if($user && $user->is_active)<span class="field-hint text-warn">{{ __('gdpr.anonymize.deactivate_first') }}</span>@endif
            </div>
            <div class="field">
                <label for="a-extra" class="field-label">{{ __('gdpr.anonymize.extra') }}</label>
                <textarea id="a-extra" wire:model.live.debounce.500ms="extra" rows="3" class="input" aria-describedby="a-extra-hint"></textarea>
                <span id="a-extra-hint" class="field-hint">{{ __('gdpr.anonymize.extra_hint', ['min' => \App\Support\Gdpr\PersonAnonymizer::MIN_TERM]) }}</span>
                @error('terms')<span class="field-error"><x-icon name="alert-circle" class="!h-4 !w-4" />{{ $message }}</span>@enderror
            </div>

            {{-- Anteprima --}}
            <div class="rounded-[var(--radius-md)] border border-line-soft p-3" aria-live="polite">
                <h3 class="mb-2 font-semibold">{{ __('gdpr.anonymize.preview') }}</h3>
                @if($terms === [])
                    <p class="text-sm text-muted">{{ __('gdpr.anonymize.preview_empty') }}</p>
                @else
                    <p class="mb-2 text-sm text-muted">{{ trans_choice('gdpr.anonymize.terms', count($terms), ['n' => count($terms)]) }}</p>
                    @if($preview === [])
                        <p class="text-sm">{{ __('gdpr.anonymize.no_matches') }}</p>
                    @else
                        <table class="table">
                            <thead><tr><th>{{ __('gdpr.anonymize.where') }}</th><th class="text-right">{{ __('gdpr.anonymize.rows') }}</th></tr></thead>
                            <tbody>@foreach($preview as $where => $n)<tr><td>{{ __('gdpr.columns.'.$where) }}</td><td class="num text-right">{{ $n }}</td></tr>@endforeach</tbody>
                        </table>
                    @endif
                    @if($user)<p class="mt-2 text-sm text-fg-2">{{ __('gdpr.anonymize.account_effects') }}</p>@endif
                @endif
            </div>
            <p class="text-sm text-muted">{{ __('gdpr.anonymize.limits') }}</p>

            <div class="max-w-xs"><x-form.input name="confirm" :label="__('gdpr.anonymize.confirm')" wire:model="confirm" autocomplete="off" /></div>
            <button type="submit" class="btn btn-primary self-start" @disabled($terms === [] || ($user && $user->is_active))><x-icon name="shield" />{{ __('gdpr.anonymize.submit') }}</button>
        </form>
    </section>

    @if($anonymized->isNotEmpty())
        <section class="card overflow-hidden" aria-labelledby="a-done">
            <h2 id="a-done" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('gdpr.anonymize.list') }}</h2>
            <ul class="divide-y divide-line-soft">@foreach($anonymized as $a)<li class="flex justify-between px-4 py-2 text-sm"><span>{{ $a->name }}</span><span class="text-muted">{{ $a->anonymized_at->translatedFormat('j M Y') }}</span></li>@endforeach</ul>
        </section>
    @endif
</div>
