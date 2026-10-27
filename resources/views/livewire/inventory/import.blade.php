<div class="mx-auto flex max-w-5xl flex-col gap-5">
    <a href="{{ route('inventory.parts.index') }}" class="btn btn-ghost btn-sm self-start"><x-icon name="arrow-left" class="!w-4 !h-4" />{{ __('inventory.title') }}</a>

    @if($result)
        {{-- Fatto --}}
        <section class="card flex flex-col items-center gap-4 px-6 py-10 text-center" role="status">
            <span class="grid h-16 w-16 place-items-center rounded-full bg-surface-2 text-ok"><x-icon name="check-circle" class="!w-9 !h-9" /></span>
            <h2 class="text-xl font-bold">{{ __('assets.import.done_title') }}</h2>
            <p class="text-fg-2">{{ __('assets.import.done_text', $result) }}</p>
            <div class="flex flex-wrap justify-center gap-2">
                <a href="{{ route('inventory.parts.index') }}" class="btn btn-primary"><x-icon name="box" />{{ __('inventory.import.go_list') }}</a>
                <button type="button" class="btn btn-secondary" wire:click="restart">{{ __('assets.import.another') }}</button>
            </div>
        </section>
    @elseif($preview)
        {{-- Anteprima --}}
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="imp-preview">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="imp-preview" class="flex-1 text-lg font-bold">{{ __('assets.import.preview_title') }}</h2>
                <span class="text-sm text-muted"><x-icon name="file-spreadsheet" class="!w-4 !h-4" /> {{ $fileName }}</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-[var(--radius-md)] border border-line p-4"><div class="num text-3xl font-bold text-ok">{{ $preview['create'] }}</div><div class="text-sm text-fg-2">{{ __('assets.import.will_create') }}</div></div>
                <div class="rounded-[var(--radius-md)] border border-line p-4"><div class="num text-3xl font-bold text-accent-fg">{{ $preview['update'] }}</div><div class="text-sm text-fg-2">{{ __('assets.import.will_update') }}</div></div>
                <div class="rounded-[var(--radius-md)] border border-line p-4"><div @class(['num text-3xl font-bold', 'text-ko' => $preview['invalid'] > 0, 'text-muted' => $preview['invalid'] === 0])>{{ $preview['invalid'] }}</div><div class="text-sm text-fg-2">{{ __('assets.import.will_skip') }}</div></div>
            </div>
            @if($preview['new_suppliers'] !== [])
                <div class="alert alert-warn" role="note"><x-icon name="info" /><span>{{ __('inventory.import.new_suppliers', ['list' => implode(', ', array_slice($preview['new_suppliers'], 0, 12)).(count($preview['new_suppliers']) > 12 ? '…' : '')]) }}</span></div>
            @endif
            @if($preview['stock_ignored'] > 0)
                <div class="alert alert-info" role="note"><x-icon name="info" /><span>{{ trans_choice('inventory.import.stock_ignored', $preview['stock_ignored'], ['count' => $preview['stock_ignored']]) }}</span></div>
            @endif
            @if($preview['unknown_columns'] !== [])
                <p class="text-sm text-muted">{{ __('assets.import.unknown_columns', ['list' => implode(', ', $preview['unknown_columns'])]) }}</p>
            @endif

            @if($preview['errors'] !== [])
                <div>
                    <h3 class="mb-2 font-semibold">{{ __('assets.import.errors_title') }}</h3>
                    <div class="max-h-80 overflow-auto rounded-[var(--radius-md)] border border-line">
                        <table class="table">
                            <thead><tr><th>{{ __('assets.import.line') }}</th><th>{{ __('inventory.field.code') }}</th><th>{{ __('assets.import.problem') }}</th></tr></thead>
                            <tbody>
                            @foreach($preview['errors'] as $e)
                                <tr><td class="num">{{ $e['line'] }}</td><td class="font-mono">{{ $e['code'] ?: '—' }}</td><td class="text-ko">{{ implode(' · ', $e['messages']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($preview['sample'] !== [])
                <div>
                    <h3 class="mb-2 font-semibold">{{ __('assets.import.sample_title') }}</h3>
                    <div class="overflow-x-auto rounded-[var(--radius-md)] border border-line">
                        <table class="table min-w-[640px]">
                            <thead><tr><th>{{ __('inventory.field.code') }}</th><th>{{ __('inventory.field.name') }}</th><th>{{ __('inventory.field.category') }}</th><th>{{ __('inventory.field.supplier') }}</th><th class="text-right">{{ __('inventory.import.initial') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($preview['sample'] as $row)
                                <tr>
                                    <td class="font-mono">{{ $row['code'] }}</td><td>{{ $row['name'] ?? '—' }}</td><td class="text-fg-2">{{ $row['category'] ?? '—' }}</td><td class="text-fg-2">{{ $row['supplier'] ?? '—' }}</td><td class="num text-right">{{ $row['stock'] !== null ? \App\Models\Part::formatQty($row['stock']).' '.($row['unit'] ?? '') : '—' }}</td>
                                    <td>@if($row['id'])<x-status tone="mnt">{{ __('assets.import.update_badge') }}</x-status>@else<x-status tone="ok">{{ __('assets.import.new_badge') }}</x-status>@endif</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @error('file')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror

            <div class="flex flex-wrap items-center gap-2">
                @if($preview['create'] + $preview['update'] > 0)
                    <button type="button" class="btn btn-primary btn-xl" wire:click="confirm" wire:loading.attr="disabled"><x-icon name="check" />{{ trans_choice('assets.import.confirm', $preview['create'] + $preview['update'], ['count' => $preview['create'] + $preview['update']]) }}</button>
                @endif
                <button type="button" class="btn btn-secondary" wire:click="restart">{{ __('assets.import.another') }}</button>
                @if($preview['invalid'] > 0)<span class="text-sm text-muted">{{ __('assets.import.skip_note') }}</span>@endif
            </div>
        </section>
    @else
        {{-- Caricamento --}}
        <section class="card flex flex-col gap-4 p-5" aria-labelledby="imp-upload">
            <h2 id="imp-upload" class="text-lg font-bold">{{ __('assets.import.step1') }}</h2>
            <p class="text-fg-2">{{ __('inventory.import.intro', ['max' => number_format($max, 0, ',', '.')]) }}</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inventory.import.template') }}" class="btn btn-secondary"><x-icon name="download" />{{ __('assets.import.template') }}</a>
            </div>
            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-[var(--radius-md)] border-2 border-dashed border-line px-4 py-10 text-center hover:bg-surface-2">
                <x-icon name="upload" class="!w-8 !h-8 text-accent-fg" />
                <span class="font-semibold">{{ $xlsx ? __('assets.import.choose') : __('assets.import.choose_csv') }}</span>
                <span class="text-sm text-muted">{{ __('assets.import.choose_hint') }}</span>
                <input type="file" wire:model="file" class="sr-only" accept="{{ $xlsx ? '.xlsx,.csv,.txt' : '.csv,.txt' }}">
            </label>
            <div wire:loading wire:target="file" class="text-sm text-muted">{{ __('assets.import.reading') }}</div>
            @error('file')<div class="alert alert-ko" role="alert"><x-icon name="alert-triangle" /><span>{{ $message }}</span></div>@enderror
        </section>

        <section class="card p-5" aria-labelledby="imp-cols">
            <h2 id="imp-cols" class="mb-3 text-lg font-bold">{{ __('assets.import.columns_title') }}</h2>
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[12rem_1fr]">
                @foreach($columns as $col)
                    <dt class="font-mono font-semibold">{{ $col }}@if(in_array($col, ['codice', 'nome'], true)) <span class="text-ko" title="{{ __('assets.import.required') }}">*</span>@endif</dt>
                    <dd class="text-fg-2">{{ __('inventory.import.column_help.'.$col) }}</dd>
                @endforeach
            </dl>
            <p class="mt-4 text-sm text-muted">{{ __('inventory.import.update_rule') }}</p>
        </section>
    @endif
</div>
