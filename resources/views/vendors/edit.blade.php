@extends('layouts.app')

@section('title', 'Vendor Edit')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Vendor Edit" :sub="$vendor->name" :back="route('vendors.show', $vendor)" />

<form method="POST" action="{{ route('vendors.update', $vendor) }}" x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="field">
        <label for="name">Vendor Ka Naam</label>
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

    <label class="check-x mb-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vendor->is_active))>
        <span>Active hai (maal entry mein nazar aayega)</span>
    </label>

    <div class="card-x">
        <div class="kv"><span>Current balance</span><span>{{ \App\Support\Money::format($vendor->current_balance) }}</span></div>
        <div class="kv"><span>Opening balance</span><span>{{ \App\Support\Money::format($vendor->opening_balance) }}</span></div>
        <div class="field-hint">Balance badalne ke liye admin vendor page par Adjust use kare.</div>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Update Karein</button>
        </div>
    </div>
</form>
@endsection
