@extends('layouts.app')

@section('title', 'Add Customer')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Add Customer" sub="Name + opening baqaya (lena hai)" :back="route('customers.index')" />

<form method="POST" action="{{ route('customers.store') }}"
      x-data="{ submitting: false, type: '{{ old('balance_type', 'owed') }}' }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="name">Customer name</label>
        <input type="text" id="name" name="name" class="form-control-x" value="{{ old('name') }}" maxlength="120" required autofocus autocapitalize="words" placeholder="e.g. Sir Ali / Madam Sara">
    </div>

    <div class="field">
        <label for="phone">Phone (optional)</label>
        <input type="tel" id="phone" name="phone" class="form-control-x" value="{{ old('phone') }}" maxlength="30" inputmode="tel" placeholder="03xx-xxxxxxx">
    </div>

    <div class="field">
        <span class="field-label">Opening balance (purana baqaya)</span>
        <div class="pill-group blue mb-2">
            <label class="m-0"><input type="radio" name="balance_type" value="owed" x-model="type"><span class="pill">Lena hai</span></label>
            <label class="m-0"><input type="radio" name="balance_type" value="advance" x-model="type"><span class="pill">Advance diya hua</span></label>
        </div>
        <x-money-input name="opening_balance" label="" :required="false" :compact="true"
                       hint="Pehle se jo baqaya hai woh yahan." />
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="2" maxlength="500">{{ old('notes') }}</textarea>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Save Customer</button>
        </div>
    </div>
</form>
@endsection
