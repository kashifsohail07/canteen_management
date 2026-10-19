@extends('layouts.app')

@section('title', 'Rent')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $target = (float) $obligation->target_amount;
    $paidPct = $target > 0 ? min(100, round(((float) $obligation->paid_total / $target) * 100)) : 0;
    $reservedPct = $target > 0 ? min(100, round(((float) $obligation->reserved_total / $target) * 100)) : 0;
    $month = now()->setDate($obligation->year, $obligation->month, 1)->format('F Y');
@endphp

<x-page-header title="Rent" :sub="$month" :back="$back" />

<div class="card-x">
    <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
            <div class="small text-muted fw-bold">Rent side mein abhi</div>
            <div class="fs-3 fw-bold text-primary">{{ Money::format($reserve) }}</div>
        </div>
        <div class="text-end">
            <div class="small text-muted fw-bold">Mahine ka target</div>
            <div class="fw-bold">{{ Money::format($obligation->target_amount) }}</div>
        </div>
    </div>
    <div class="progress-x mb-2" title="Rent diya"><div style="width: {{ $paidPct }}%"></div></div>
    <div class="d-flex justify-content-between small text-muted">
        <span>Rakha: <b class="text-dark">{{ Money::format($obligation->reserved_total) }}</b> ({{ $reservedPct }}%)</span>
        <span>Diya: <b class="text-dark">{{ Money::format($obligation->paid_total) }}</b></span>
        <span>Baqi: <b class="text-dark">{{ Money::format($obligation->remainingToPay()) }}</b></span>
    </div>
    <a href="{{ route('rent.pay', $entryDate ? ['date' => $entryDate] : []) }}" class="btn-xl btn-sm-x btn-ghost w-100 mt-3"><i class="bi bi-house-check-fill"></i> Rent Pay Karein (Malik Ko)</a>
</div>

<form method="POST" action="{{ route('rent.reserve') }}" id="reserve-form"
      x-data="txnForm(@js(['key' => old('idempotency_key'), 'amount' => old('amount', $suggest)]))"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <div class="divider-label">Aaj rent side rakhein</div>

    <x-money-input name="amount" label="Rakhne Wali Raqam" x-model="amount" hint="Gallay se rent reserve mein transfer hoga. Gallay mein: {{ Money::format($cashInHand) }}" />

    <div class="d-flex gap-2 mb-3" style="margin-top:-6px">
        <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="amount = '{{ (int) $suggest }}'">Suggested {{ Money::format($suggest) }}</button>
        <button type="button" class="btn-xl btn-sm-x btn-outline-x flex-fill" @click="amount = '{{ max(0, (int) ($suggest / 2)) }}'">Aadha</button>
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}">
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-house-lock-fill"></i> <span x-text="'Rent Side Rakhein ' + (n(amount) ? '(' + fmt(amount) + ')' : '')"></span></span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('rent.skip') }}" class="card-x" x-data="{ busy: false }" @submit="if (busy) { $event.preventDefault() } else { busy = true }">
    @csrf
    @if ($entryDate)<input type="hidden" name="business_date" value="{{ $entryDate }}">@endif
    <div class="card-title">Aaj rent nahi rakhna?</div>
    <div class="small text-muted mb-3">Skip karne se koi paisa nahi hilta, sirf activity mein note ho jata hai.</div>
    <input type="text" name="notes" class="form-control-x mb-3" maxlength="500" placeholder="Wajah (optional) — jaise: sale kam thi">
    <button type="submit" class="btn-xl btn-outline-x w-100" :class="{ disabled: busy }"><i class="bi bi-skip-forward-fill"></i> Aaj Skip Karein</button>
</form>
@endsection
