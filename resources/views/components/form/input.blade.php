@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])
@php
    $id = $attributes->get('id', 'f-'.$name);
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field">
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if($type !== 'password') value="{{ old($name, $value) }}" @endif
           @if($error) aria-invalid="true" @endif
           @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->except('id')->merge(['class' => 'input']) }}>
    @if($hint)<span id="{{ $id }}-hint" class="field-hint">{{ $hint }}</span>@endif
    @if($error)<span id="{{ $id }}-error" class="field-error"><x-icon name="alert-circle" class="!w-4 !h-4" />{{ $error }}</span>@endif
</div>
