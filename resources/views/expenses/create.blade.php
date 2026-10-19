@extends('layouts.app')

@section('title', 'Kharcha')
@section('main_class', 'has-savebar')

@section('content')
@php $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []); @endphp

<x-page-header title="Kharcha" :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Canteen ka koi bhi kharcha'" :back="$back" />

<form method="POST" action="{{ route('expenses.store') }}"
      x-data="txnForm(@js(['key' => old('idempotency_key'), 'amount' => old('amount', ''), 'source' => old('payment_source', 'canteen_cash')]))"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <x-money-input name="amount" label="Kharcha Amount" x-model="amount" autofocus />

    <div class="field" x-data="{ other: {{ old('new_category') ? 'true' : 'false' }} }">
        <label for="expense_category_id">Category</label>
        <select id="expense_category_id" name="expense_category_id" class="form-select-x" x-show="!other">
            <option value="">— Category chunein —</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('expense_category_id') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <input type="text" name="new_category" class="form-control-x" maxlength="60" placeholder="Nayi category ka naam"
               value="{{ old('new_category') }}" x-show="other" x-cloak :disabled="!other">
        <button type="button" class="btn btn-link px-0 fw-bold" @click="other = !other" x-text="other ? '← Maujooda category chunein' : '+ Nayi category'"></button>
    </div>

    <x-payment-source />
    <div class="field-hint" style="margin-top:-8px; margin-bottom:14px" x-show="source === 'canteen_cash'">
        Gallay mein available: <b>{{ \App\Support\Money::format($cashInHand) }}</b>
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}" placeholder="Kis cheez ka kharcha…">
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Kharcha Save Karein</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>
@endsection
