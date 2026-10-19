@extends('layouts.app')

@section('title', 'Rent Pay')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $back = route('rent.create', $entryDate ? ['date' => $entryDate] : []);
    $month = now()->setDate($obligation->year, $obligation->month, 1)->format('F Y');
    $default = bccomp($reserve, '0', 2) > 0 ? $reserve : $obligation->remainingToPay();
    $sources = [
        ['rent_reserve', 'Rent Reserve', 'Side rakhay paisay: '.Money::format($reserve), 'bi-house-lock-fill'],
        ['canteen_cash', 'Canteen Cash', 'Gallay se: '.Money::format($cashInHand), 'bi-cash-stack'],
        ['personal_pocket', 'Personal Pocket', 'Apni jeb se (owner ka lena)', 'bi-wallet2'],
    ];
@endphp

<x-page-header title="Rent Pay Karein" :sub="$month" :back="$back" />

<div class="balance-box">
    <span>{{ $month }} baqi rent</span>
    <b class="text-owed">{{ Money::format($obligation->remainingToPay()) }}</b>
</div>

<form method="POST" action="{{ route('rent.pay.store') }}"
      x-data="txnForm(@js(['key' => old('idempotency_key'), 'amount' => old('amount', bccomp($default, '0', 2) > 0 ? rtrim(rtrim($default, '0'), '.') : ''), 'source' => old('payment_source', 'rent_reserve')]))"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <x-money-input name="amount" label="Rent Amount" x-model="amount" />
    <x-payment-source :options="$sources" label="Rent Kahan Se Diya?" />

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}">
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-house-check-fill"></i> Rent Save Karein</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>
@endsection
