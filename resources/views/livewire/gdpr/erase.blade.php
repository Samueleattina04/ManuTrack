<div class="mx-auto flex max-w-4xl flex-col gap-5">
    <x-gdpr-tabs current="erase" />
    <section class="card flex flex-col gap-4 border-ko p-4 sm:p-5" aria-labelledby="e-title">
        <h2 id="e-title" class="text-base font-bold text-ko">{{ __('gdpr.erase.title') }}</h2>
        <p class="max-w-prose text-fg-2">{{ __('gdpr.erase.intro') }}</p>
        <div class="grid gap-2 sm:grid-cols-2">
            <p><strong class="num">{{ number_format($rows, 0, ',', '.') }}</strong> {{ __('gdpr.erase.rows', ['tables' => $tables]) }}</p>
            <ul class="text-sm text-fg-2">
                @foreach($folders as $label => $f)<li>{{ $label }}: <span class="num">{{ $f['files'] }}</span> file, {{ \Illuminate\Support\Number::fileSize($f['bytes'], 1) }}</li>@endforeach
            </ul>
        </div>
        <div class="alert alert-warn" role="note"><x-icon name="alert-triangle" />
            <div class="flex flex-col gap-1">
                <strong>{{ __('gdpr.erase.outside_title') }}</strong>
                <ul class="list-disc pl-5">@foreach(__('gdpr.erase.outside') as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
        </div>
        @unless($recentExport)
            <div class="alert alert-info" role="note"><x-icon name="info" /><span>{{ __('gdpr.erase.no_export') }} <a href="{{ route('gdpr.exports') }}" class="underline">{{ __('gdpr.tabs.exports') }}</a></span></div>
        @endunless
        <form wire:submit="erase" class="flex flex-col gap-3">
            <label class="flex min-h-[44px] items-start gap-2.5"><input type="checkbox" wire:model="understood" class="mt-1 h-5 w-5 accent-[var(--mt-accent)]"><span>{{ __('gdpr.erase.understood') }}</span></label>
            @error('understood')<span class="field-error">{{ $message }}</span>@enderror
            <div class="max-w-md"><x-form.input name="phrase" :label="__('gdpr.erase.phrase', ['phrase' => $phraseText])" wire:model="phrase" autocomplete="off" /></div>
            <div class="max-w-md"><x-form.input name="password" type="password" :label="__('gdpr.erase.password')" wire:model="password" autocomplete="current-password" /></div>
            <button type="submit" class="btn self-start bg-[var(--mt-ko)] text-white" wire:confirm="{{ __('gdpr.erase.last_confirm') }}"><x-icon name="trash" />{{ __('gdpr.erase.submit') }}</button>
        </form>
    </section>
</div>
