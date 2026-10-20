@extends('layouts.app')

@section('title', 'Add Vendor')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Add Vendor" sub="Name + opening balance (purana baqaya)" :back="route('vendors.index')" />

<form method="POST" action="{{ route('vendors.store') }}" x-data="{ submitting: false, type: '{{ old('balance_type', 'owed') }}' }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="name">Vendor name</label>
        <input type="text" id="name" name="name" class="form-control-x" value="{{ old('name') }}" maxlength="120" required autofocus autocapitalize="words" placeholder="e.g. Pepsi">
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
                       hint="Jo pehle se baqaya hai woh yahan likho. Baad mein Vendors → vendor open karke badal sakte ho." />
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
