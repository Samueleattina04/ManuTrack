<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <x-gdpr-tabs current="retention" />
    <section class="card flex flex-col gap-4 p-4 sm:p-5" aria-labelledby="r-title">
        <h2 id="r-title" class="text-base font-bold">{{ __('gdpr.retention.title') }}</h2>
        <p class="max-w-prose text-fg-2">{{ __('gdpr.retention.intro') }}</p>
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="anonymizeAfter" type="number" min="1" max="240" :label="__('gdpr.retention.anonymize')" :hint="__('gdpr.retention.anonymize_hint')" wire:model.live.debounce.400ms="anonymizeAfter" />
            <x-form.input name="auditMonths" type="number" :min="$min" max="240" :label="__('gdpr.retention.audit')" :hint="__('gdpr.retention.audit_hint', ['min' => $min])" wire:model.live.debounce.400ms="auditMonths" />
            <ul class="list-disc pl-5 text-sm text-fg-2 sm:col-span-2">
                @foreach(__('gdpr.retention.fixed') as $line)<li>{{ $line }}</li>@endforeach
            </ul>
            <div class="sm:col-span-2"><button type="submit" class="btn btn-primary"><x-icon name="check" />{{ __('gdpr.retention.save') }}</button></div>
        </form>
    </section>
    <section class="card flex flex-col gap-2 p-4 sm:p-5" aria-labelledby="r-preview" aria-live="polite">
        <h2 id="r-preview" class="text-base font-bold">{{ __('gdpr.retention.preview') }}</h2>
        <p class="text-sm text-muted">{{ __('gdpr.retention.preview_hint') }}</p>
        <p>{{ trans_choice('gdpr.retention.preview_users', $users, ['n' => $users]) }}</p>
        <p>{{ trans_choice('gdpr.retention.preview_audit', $audit, ['n' => $audit]) }}</p>
    </section>
</div>
