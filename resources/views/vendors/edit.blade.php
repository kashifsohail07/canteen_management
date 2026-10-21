@extends('layouts.app')

@section('title', 'Edit Vendor')
@section('main_class', 'has-savebar')

@section('content')
@php
    $usesQty = (bool) old('uses_quantity', $vendor->hasFixedRate());
@endphp

<x-page-header title="Edit Vendor" :sub="$vendor->name" :back="route('vendors.show', $vendor)" />

<form method="POST" action="{{ route('vendors.update', $vendor) }}"
      x-data="{
          submitting: false,
          usesQty: {{ $usesQty ? 'true' : 'false' }}
      }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="field">
        <label for="name">Vendor name</label>
        <input type="text" id="name" name="name" class="form-control-x" value="{{ old('name', $vendor->name) }}" maxlength="120" required autocapitalize="words">
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" class="form-control-x" value="{{ old('phone', $vendor->phone) }}" maxlength="30" inputmode="tel">
    </div>

    <div class="field">
        <label for="notes">Note</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="3" maxlength="500">{{ old('notes', $vendor->notes) }}</textarea>
    </div>

    <div class="card-x mb-3">
        <label class="check-x mb-0">
            <input type="checkbox" name="uses_quantity" value="1" x-model="usesQty">
            <span>Quantity wala vendor (qty × rate)</span>
        </label>
        <div class="field-hint mt-2 mb-0">
            Samosay / roll — Add Mall mein sirf qty, rate fix.
        </div>

        <div class="mt-3" x-show="usesQty" x-cloak>
            <div class="field">
                <label for="default_item_name">Item name</label>
                <input type="text" id="default_item_name" name="default_item_name" class="form-control-x"
                       value="{{ old('default_item_name', $vendor->default_item_name) }}" maxlength="120" placeholder="Samosay / Roll"
                       :required="usesQty">
            </div>
            <input type="hidden" name="default_unit" value="pcs" x-bind:disabled="!usesQty">
            <x-money-input name="default_rate" id="default_rate" label="Price per pcs (fix)" :required="false" :compact="true"
                           :value="old('default_rate', $vendor->default_rate !== null ? \App\Support\Money::of($vendor->default_rate) : '')"
                           hint="e.g. Rs. 20" />
            @error('default_rate')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <label class="check-x mb-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active))>
        <span>Active (Add Mall list mein dikhe)</span>
    </label>

    <div class="card-x">
        <div class="kv"><span>Current balance</span><span>{{ \App\Support\Money::format($vendor->current_balance) }}</span></div>
        <div class="kv"><span>Opening balance</span><span>{{ \App\Support\Money::format($vendor->opening_balance) }}</span></div>
        <div class="field-hint">Balance badalne ke liye vendor page par Opening / Adjust use karo.</div>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Update</button>
        </div>
    </div>
</form>

@if ($isAdmin)
    <form method="POST" action="{{ route('vendors.destroy', $vendor) }}" class="mt-3"
          onsubmit="return confirm('{{ addslashes($vendor->name) }} delete karein?\n\nList se hat jayega. Purana ledger safe rahega.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-xl btn-sm-x w-100" style="color:#b91c1c;background:#fef2f2;border:1px solid #fecaca">
            <i class="bi bi-trash3"></i> Delete Vendor
        </button>
    </form>
@endif
@endsection
