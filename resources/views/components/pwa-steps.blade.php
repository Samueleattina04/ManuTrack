@props(['platform'])
{{-- Illustrazioni animate dei passaggi di installazione (iPhone o Android) --}}
@php $icon = \App\Support\Brand\Brand::iconUrl('apple-touch-icon.png'); @endphp
<ol class="grid gap-3 sm:grid-cols-3" role="list">
    {{-- 1. Pulsante da toccare --}}
    <li class="card flex flex-col gap-3 p-4">
        <div class="wiz-screen" aria-hidden="true">
            @if($platform === 'ios')
                <div class="wiz-bar wiz-bar-bottom">
                    <span class="wiz-ico"><x-icon name="chevron-right" class="rotate-180" /></span>
                    <span class="wiz-ico"><x-icon name="chevron-right" /></span>
                    <span class="wiz-ico wiz-hl"><x-icon name="share" /></span>
                    <span class="wiz-ico"><x-icon name="box" /></span>
                    <span class="wiz-ico"><x-icon name="clipboard-list" /></span>
                </div>
            @else
                <div class="wiz-bar wiz-bar-top">
                    <span class="wiz-url">manutrack…</span>
                    <span class="wiz-ico wiz-hl"><svg viewBox="0 0 24 24" class="mt-icon"><circle cx="12" cy="5" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="19" r="1.5" fill="currentColor"/></svg></span>
                </div>
            @endif
        </div>
        <div><span class="step-n">1</span> <strong>{{ __('pwa.wizard.'.$platform.'_1') }}</strong><p class="text-sm text-muted">{{ __('pwa.wizard.'.$platform.'_1_hint') }}</p></div>
    </li>
    {{-- 2. Voce del menu --}}
    <li class="card flex flex-col gap-3 p-4">
        <div class="wiz-screen" aria-hidden="true">
            <div class="wiz-sheet">
                <span class="wiz-row"><x-icon name="clipboard-list" />{{ $platform === 'ios' ? 'Copia' : 'Nuova scheda' }}</span>
                <span class="wiz-row"><x-icon name="sparkles" />{{ $platform === 'ios' ? 'Aggiungi ai preferiti' : 'Cronologia' }}</span>
                <span class="wiz-row wiz-hl-row"><x-icon name="plus" />{{ $platform === 'ios' ? 'Aggiungi alla schermata Home' : 'Installa app' }}</span>
            </div>
        </div>
        <div><span class="step-n">2</span> <strong>{{ __('pwa.wizard.'.$platform.'_2') }}</strong><p class="text-sm text-muted">{{ __('pwa.wizard.'.$platform.'_2_hint') }}</p></div>
    </li>
    {{-- 3. Icona nella schermata Home --}}
    <li class="card flex flex-col gap-3 p-4">
        <div class="wiz-screen" aria-hidden="true">
            <div class="wiz-home">
                @for($i = 0; $i < 7; $i++)<span class="wiz-app"></span>@endfor
                <span class="wiz-app wiz-app-mt"><img src="{{ $icon }}" alt=""></span>
            </div>
        </div>
        <div><span class="step-n">3</span> <strong>{{ __('pwa.wizard.'.$platform.'_3') }}</strong><p class="text-sm text-muted">{{ __('pwa.wizard.'.$platform.'_3_hint') }}</p></div>
    </li>
</ol>
