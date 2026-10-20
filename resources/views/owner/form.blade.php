@extends('layouts.app')

@section('title', $type === 'deposit' ? 'Owner Deposit' : 'Owner Repayment')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $isDeposit = $type === 'deposit';
    $action = $isDeposit ? route('owner.deposit.store') : route('owner.repayment.store');
@endphp

<x-page-header :title="$isDeposit ? 'Owner Ne Cash Diya' : 'Owner Ko Wapis Kiya'"
               :sub="$isDeposit ? 'Gallay mein cash barhega, owner ka lena barhega' : 'Gallay se cash jayega, owner ka lena kam hoga'"
               :back="route('owner.index')" />

<div class="balance-box">
    <span>Owner ka baqaya</span>
    <b class="text-owed">{{ Money::format($recoverable) }}</b>
</div>

<form method="POST" action="{{ $action }}"
      x-data="txnForm(@js(['key' => old('idempotency_key'), 'amount' => old('amount', '')]))"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <x-money-input name="amount" label="Amount" x-model="amount" autofocus
                   :hint="$isDeposit ? null : 'Gallay mein available: '.Money::format($cashInHand).' · Zyada se zyada '.Money::format($recoverable).' wapis ho sakta hai.'" />

    @unless ($isDeposit)
        <div class="d-flex gap-2 mb-3" style="margin-top:-6px">
            <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="amount = '{{ $recoverable }}'">Poora wapis ({{ Money::format($recoverable) }})</button>
        </div>
    @endunless

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}">
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl {{ $isDeposit ? 'btn-save' : 'btn-blue' }}" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> {{ $isDeposit ? 'Deposit Save Karein' : 'Repayment Save Karein' }}</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>
@endsection
