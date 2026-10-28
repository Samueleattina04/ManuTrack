<div class="mx-auto flex max-w-4xl flex-col gap-5" @if($pending) wire:poll.5s @endif>
    <x-gdpr-tabs current="exports" />
    <section class="card flex flex-col gap-3 p-4 sm:p-5" aria-labelledby="g-export">
        <h2 id="g-export" class="text-base font-bold">{{ __('gdpr.export.title') }}</h2>
        <p class="max-w-prose text-fg-2">{{ __('gdpr.export.intro') }}</p>
        <ul class="list-disc pl-5 text-sm text-fg-2">
            @foreach(__('gdpr.export.contains') as $line)<li>{{ $line }}</li>@endforeach
        </ul>
        <p class="text-sm text-muted">{{ __('gdpr.export.excluded', ['days' => \App\Models\DataExport::KEEP_DAYS]) }}</p>
        <button type="button" class="btn btn-primary self-start" wire:click="start" @disabled($pending)><x-icon name="download" />{{ __('gdpr.export.start') }}</button>
    </section>

    <section class="card overflow-hidden" aria-labelledby="g-list">
        <h2 id="g-list" class="border-b border-line-soft px-4 py-3 text-base font-bold">{{ __('gdpr.export.list') }}</h2>
        @if($exports->isEmpty())
            <p class="px-4 py-6 text-center text-muted">{{ __('gdpr.export.none') }}</p>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach($exports as $e)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="e-{{ $e->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $e->created_at->translatedFormat('j M Y H:i') }} <span class="text-sm font-normal text-muted">· {{ $e->user->name ?? '—' }}</span></p>
                            <p class="text-sm text-muted">
                                @if($e->status === 'pronto')
                                    {{ __('gdpr.export.ready', ['tables' => $e->tables_count, 'files' => $e->files_count, 'size' => \Illuminate\Support\Number::fileSize((int) $e->size, 1)]) }}
                                    · {{ $e->isDownloadable() ? __('gdpr.export.expires', ['date' => $e->expires_at?->translatedFormat('j M Y')]) : __('gdpr.export.expired') }}
                                @elseif($e->status === 'errore')
                                    <span class="text-ko">{{ __('gdpr.export.failed') }}</span>
                                @else
                                    {{ __('gdpr.export.working') }}
                                @endif
                            </p>
                        </div>
                        <x-status :tone="['in_coda' => 'warn', 'in_corso' => 'mnt', 'pronto' => 'ok', 'errore' => 'ko'][$e->status]">{{ __('gdpr.export.status.'.$e->status) }}</x-status>
                        @if($e->isDownloadable())
                            <a href="{{ route('gdpr.exports.download', $e) }}" class="btn btn-secondary btn-sm"><x-icon name="download" class="!h-4 !w-4" />{{ __('gdpr.export.download') }}</a>
                        @endif
                        @unless($e->isPending())
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="delete({{ $e->id }})" wire:confirm="{{ __('gdpr.export.delete_confirm') }}"><x-icon name="trash" class="!h-4 !w-4" /><span class="sr-only">{{ __('gdpr.export.delete') }}</span></button>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
