@extends('layouts.app')

@section('title', 'Vendor Ko Diye')
@section('main_class', 'has-savebar')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $vendorData = $vendor ? [
        'id' => $vendor->id, 'name' => $vendor->name, 'balance' => \App\Support\Money::of($vendor->current_balance),
        'balance_formatted' => \App\Support\Money::format($vendor->current_balance), 'phone' => $vendor->phone,
    ] : null;
@endphp

<x-page-header
    title="Shaam Ka Hisab"
    :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Samosay jaisa vendor: din bhar maal, ab jitna cash hai utna do'"
    :back="$back"
/>

<div class="settle-cash-banner">
    <div>
        <div class="small text-muted">Gallay mein available cash</div>
        <div class="settle-cash-amount">{{ \App\Support\Money::format($cashInHand) }}</div>
    </div>
    <i class="bi bi-wallet2"></i>
</div>

<form method="POST" action="{{ route('payments.store') }}"
      x-data="txnForm(@js([
          'key' => old('idempotency_key'),
          'vendor' => $vendorData,
          'amount' => old('amount', ''),
          'source' => old('payment_source', 'canteen_cash'),
          'cashAvailable' => $cashInHand,
          'todayUrl' => route('purchases.today-deliveries'),
      ]))"
      @vendor-selected="setVendor($event.detail)"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <x-vendor-autocomplete :vendor="$vendor" focus="amount" />

    <div class="balance-box" x-show="vendor" x-cloak>
        <span>Kul dena hai (baqaya)</span>
        <b :class="balanceClass(prev)" x-text="describeBalance(prev)"></b>
    </div>

    {{-- Aaj is vendor se kitna maal aaya --}}
    <div class="today-box" x-show="vendor && todayLines.length" x-cloak>
        <div class="today-head">
            <i class="bi bi-box-seam"></i>
            <span>Aaj ka maal (udhaar)</span>
            <b x-text="todayQtyTotal + ' pcs · ' + fmt(todayAmountTotal)"></b>
        </div>
        <ul class="today-list">
            <template x-for="(line, i) in todayLines" :key="i">
                <li>
                    <span x-text="line.time"></span>
                    <span x-text="line.item_name"></span>
                    <b x-text="line.quantity + (line.unit ? ' ' + line.unit : '')"></b>
                    <span x-text="fmt(line.line_total)"></span>
                </li>
            </template>
        </ul>
        <p class="hint-line mb-0 mt-2">Din bhar pehle maal save hua — ab ek dafa payment.</p>
    </div>

    <x-money-input name="amount" label="Ab kitne dena hai?" x-model="amount" />

    <div class="settle-actions mb-3" x-show="vendor" x-cloak>
        <button type="button" class="btn-xl btn-settle" @click="fillSettleSuggest()"
                :disabled="settleSuggest <= 0">
            <i class="bi bi-cash-stack"></i>
            <span>
                Jitna cash hai
                <small x-show="settleSuggest > 0" x-text="fmt(settleSuggest)"></small>
            </span>
        </button>
        <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="amount = String(prev)"
                :disabled="prev <= 0" x-show="prev > 0">
            Poora baqaya
        </button>
    </div>

    <div class="field-hint mb-3" x-show="vendor && settleSuggest > 0 && settleSuggest < prev" x-cloak>
        Baqaya <b x-text="fmt(prev)"></b> hai, cash <b>{{ \App\Support\Money::format($cashInHand) }}</b> —
        jitna milega utna do, baqi kal chalega.
    </div>

    <x-payment-source />

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}" placeholder="Shaam ka hisab…">
    </div>

    <div class="save-bar">
        <div class="summary" x-show="vendor && n(amount) > 0" x-cloak>
            <span>Payment ke baad baqaya</span>
            <b :class="balanceClass(newBalancePay)" x-text="describeBalance(newBalancePay)"></b>
        </div>
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-send-check-fill"></i> Hisab Save Karein</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>
@endsection
