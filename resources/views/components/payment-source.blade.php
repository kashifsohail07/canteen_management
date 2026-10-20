{{-- Radio cards bound to the parent txnForm `source` model. --}}
@props(['name' => 'payment_source', 'options' => null, 'model' => 'source', 'label' => 'Paisay Kahan Se?'])
@php
    $options ??= [
        ['canteen_cash', 'Canteen Cash', 'Gallay se', 'bi-cash-stack'],
        ['personal_pocket', 'Personal Pocket', 'Apni jeb se', 'bi-wallet2'],
    ];
@endphp
<div class="field">
    <span class="field-label">{{ $label }}</span>
    <div class="src-toggle {{ count($options) > 2 ? 'three' : '' }}">
        @foreach ($options as [$value, $title, $hint, $icon])
            <label class="m-0">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" x-model="{{ $model }}">
                <span class="src-option">
                    <i class="bi {{ $icon }}"></i>
                    <span>{{ $title }}<small>{{ $hint }}</small></span>
                </span>
            </label>
        @endforeach
    </div>
</div>
