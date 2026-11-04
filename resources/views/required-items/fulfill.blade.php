@extends('layouts.app')

@section('title', 'Mark Available')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
<x-page-header title="Mark Available" :sub="$item->title" :back="route('required-items.index')" />

<div class="card-x mb-3">
    <div class="fw-bold">{{ $item->title }}</div>
    <div class="d-flex flex-wrap gap-1 mt-2">
        @if ($item->class_name)
            <span class="badge-soft blue">{{ $item->class_name }}</span>
        @endif
        <span class="badge-soft orange">Needed</span>
    </div>
    <div class="small text-muted mt-2">
        @if ($item->quantity_requested)
            Wanted {{ rtrim(rtrim(number_format((float) $item->quantity_requested, 3, '.', ''), '0'), '.') }}{{ $item->unit ? ' '.$item->unit : '' }}
            ·
        @endif
        Added {{ $item->created_at->format('d M Y') }}
    </div>
    @if ($item->notes)
        <div class="small mt-2">{{ $item->notes }}</div>
    @endif
</div>

<form method="POST" action="{{ route('required-items.fulfill.store', $item) }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="quantity_received">Quantity received</label>
        <input type="number" id="quantity_received" name="quantity_received" class="form-control-x"
               value="{{ old('quantity_received', $item->quantity_requested ? rtrim(rtrim((string) $item->quantity_requested, '0'), '.') : '') }}"
               min="0.001" step="any" inputmode="decimal" required
               placeholder="How many came from market">
        @error('quantity_received')<div class="field-error">{{ $message }}</div>@enderror
        @if ($item->unit)
            <div class="field-hint">Unit: {{ $item->unit }}</div>
        @endif
    </div>

    <x-money-input name="price" label="Price" :required="true" :compact="true"
                   :value="old('price')" hint="What you paid for this stock" />

    <div class="field">
        <label for="notes">Note (optional)</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="2" maxlength="500">{{ old('notes', $item->notes) }}</textarea>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <i class="bi bi-check2-circle"></i> Mark Available
            </button>
        </div>
    </div>
</form>
@endsection
