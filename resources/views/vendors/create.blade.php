@extends('layouts.app')

@section('title', 'Add Vendor')
@section('main_class', 'has-savebar')

@section('content')
@php
    $usesQty = old('uses_quantity', false);
@endphp

<x-page-header title="Add Vendor" sub="Name + opening balance (purana baqaya)" :back="route('vendors.index')" />

<form method="POST" action="{{ route('vendors.store') }}"
      x-data="{
          submitting: false,
          type: '{{ old('balance_type', 'owed') }}',
          usesQty: {{ $usesQty ? 'true' : 'false' }}
      }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="name">Vendor name</label>
        <input type="text" id="name" name="name" class="form-control-x" value="{{ old('name') }}" maxlength="120" required autofocus autocapitalize="words" placeholder="e.g. Pepsi / Samosay">
    </div>

    <div class="field">
        <label for="phone">Phone (optional)</label>
        <input type="tel" id="phone" name="phone" class="form-control-x" value="{{ old('phone') }}" maxlength="30" inputmode="tel" placeholder="03xx-xxxxxxx">
    </div>

    <div class="field">
        <span class="field-label">Opening balance (purana baqaya)</span>
        <div class="pill-group blue mb-2">
            <label class="m-0"><input type="radio" name="balance_type" value="owed" x-model="type"><span class="pill">Dena hai</span></label>
            <label class="m-0"><input type="radio" name="balance_type" value="advance" x-model="type"><span class="pill">Advance diya hua</span></label>
        </div>
        <x-money-input name="opening_balance" label="" :required="false" :compact="true"
                       hint="Jo pehle se baqaya hai woh yahan likho." />
    </div>

    <div class="card-x mb-3">
        <label class="check-x mb-0">
            <input type="checkbox" name="uses_quantity" value="1" x-model="usesQty">
            <span>Quantity wala vendor (qty × rate)</span>
        </label>
        <div class="field-hint mt-2 mb-0">
            Samosay / roll jaisa — Add Mall mein sirf qty likho, bill khud banega.
        </div>

        <div class="mt-3" x-show="usesQty" x-cloak>
            <div class="field">
                <label for="default_item_name">Item name</label>
                <input type="text" id="default_item_name" name="default_item_name" class="form-control-x"
                       value="{{ old('default_item_name') }}" maxlength="120" placeholder="Samosay / Roll"
                       :required="usesQty">
            </div>
            <input type="hidden" name="default_unit" value="pcs">
            <x-money-input name="default_rate" id="default_rate" label="Price per pcs (fix)" :required="false" :compact="true"
                           :value="old('default_rate')"
                           hint="e.g. Rs. 20 — yeh rate fix rahega." />
            @error('default_rate')<div class="field-error">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="2" maxlength="500">{{ old('notes') }}</textarea>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Save Vendor</button>
        </div>
    </div>
</form>
@endsection
