@props(['name', 'label' => 'Amount', 'id' => null, 'value' => null, 'compact' => false, 'required' => true, 'signed' => false, 'hint' => null])
@php($id = $id ?? $name)
<div class="field">
    @if ($label !== '')<label for="{{ $id }}">{{ $label }}</label>@endif
    <div class="money-field {{ $compact ? 'compact' : '' }}">
        <span class="prefix">Rs.</span>
        <input
            type="text"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            inputmode="{{ $signed ? 'text' : 'decimal' }}"
            data-money="{{ $signed ? 'signed' : 'positive' }}"
            autocomplete="off"
            placeholder="0"
            enterkeyhint="done"
            @if ($required) required @endif
            {{ $attributes->merge(['class' => 'form-control-x'.($errors->has($name) ? ' is-invalid' : '')]) }}
        >
    </div>
    @if ($hint)<div class="field-hint">{{ $hint }}</div>@endif
    @error($name)<div class="field-error">{{ $message }}</div>@enderror
</div>
