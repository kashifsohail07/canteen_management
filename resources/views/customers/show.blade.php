@extends('layouts.app')

@section('title', $customer->name)

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $bal = bccomp((string) $customer->current_balance, '0', 2);
@endphp

<x-page-header :title="$customer->name" :sub="$customer->phone ?: 'Customer udhaar'" :back="route('customers.index')">
    <a href="{{ route('customers.edit', $customer) }}" class="back-btn" aria-label="Edit"><i class="bi bi-pencil-fill"></i></a>
</x-page-header>

<section class="hero-cash" style="{{ $bal > 0 ? 'background: radial-gradient(circle at 90% 0%, rgba(245,158,11,.5) 0, transparent 55%), linear-gradient(135deg, #b45309, #d97706);' : '' }}">
    <div class="label">
        <i class="bi bi-person-badge-fill"></i>
        {{ $bal > 0 ? 'Lena Hai' : ($bal < 0 ? 'Advance Diya Hua' : 'Hisab Clear') }}
        @unless ($customer->is_active)<span class="badge-soft red ms-1">Inactive</span>@endunless
    </div>
    <div class="amount">{{ Money::format(Money::abs($customer->current_balance)) }}</div>
    <div class="meta"><span>Opening {{ Money::format($customer->opening_balance) }}</span></div>
</section>

<div class="action-grid">
    <a href="{{ route('customers.udhaar', ['customer' => $customer->id]) }}" class="action-tile tile-orange" style="min-height:84px">
        <i class="bi bi-journal-plus"></i><span>Add Udhaar</span>
    </a>
    <a href="{{ route('customers.payment', ['customer' => $customer->id]) }}" class="action-tile tile-green" style="min-height:84px">
        <i class="bi bi-cash-coin"></i><span>Receive Pay</span>
    </a>
</div>

@if ($customer->notes)
    <div class="card-x small"><i class="bi bi-sticky text-muted"></i> {{ $customer->notes }}</div>
@endif

@if ($isAdmin)
    @php
        $openOpening = $errors->has('opening_balance')
            || (! $errors->has('new_balance') && bccomp((string) $customer->current_balance, '0', 2) === 0);
        $defaultTab = $errors->has('new_balance') ? 'adjust' : ($openOpening ? 'opening' : '');
    @endphp
    <div class="card-x" x-data="{ tab: @js($defaultTab) }">
        <div class="card-title">Opening balance / Adjust</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-xl btn-sm-x flex-fill" :class="tab === 'opening' ? 'btn-blue' : 'btn-ghost'" @click="tab = tab === 'opening' ? '' : 'opening'">Opening Balance</button>
            <button type="button" class="btn-xl btn-sm-x flex-fill" :class="tab === 'adjust' ? 'btn-blue' : 'btn-ghost'" @click="tab = tab === 'adjust' ? '' : 'adjust'">Balance Adjust</button>
        </div>

        <form method="POST" action="{{ route('customers.opening-balance', $customer) }}" class="mt-3" x-show="tab === 'opening'" x-cloak>
            @csrf
            <x-money-input name="opening_balance" id="opening_balance" label="Opening balance" :signed="true" :compact="true" :value="Money::of($customer->opening_balance)"
                           hint="Positive = lena hai, negative = advance." />
            <div class="field">
                <label for="opening_reason">Reason (required)</label>
                <input type="text" id="opening_reason" name="reason" class="form-control-x" maxlength="500" required value="{{ old('reason') }}">
            </div>
            <button type="submit" class="btn-xl btn-sm-x btn-blue w-100">Save Opening</button>
        </form>

        <form method="POST" action="{{ route('customers.adjust-balance', $customer) }}" class="mt-3" x-show="tab === 'adjust'" x-cloak>
            @csrf
            <x-money-input name="new_balance" id="new_balance" label="New current balance" :signed="true" :compact="true" :value="Money::of($customer->current_balance)"
                           hint="Positive = lena hai, negative = advance." />
            <div class="field">
                <label for="adjust_reason">Reason (required)</label>
                <input type="text" id="adjust_reason" name="reason" class="form-control-x" maxlength="500" required value="{{ old('reason') }}">
            </div>
            <button type="submit" class="btn-xl btn-sm-x btn-blue w-100">Adjust Balance</button>
        </form>
    </div>
@endif

<div class="section-title"><span>Ledger</span><small>{{ $entries->total() }} entries</small></div>

@if ($entries->isEmpty())
    <div class="act-list"><div class="empty"><i class="bi bi-journal"></i>Abhi koi entry nahi.</div></div>
@else
    <div class="list-card">
        @foreach ($entries as $entry)
            @php
                $isDebit = bccomp((string) $entry->debit, '0', 2) > 0;
            @endphp
            <div class="list-row">
                <span class="act-icon" style="{{ $isDebit ? 'color:var(--balance-orange);background:var(--balance-orange-soft)' : 'color:var(--brand-green);background:var(--brand-green-soft)' }}">
                    <i class="bi {{ $isDebit ? 'bi-journal-plus' : 'bi-cash-coin' }}"></i>
                </span>
                <div class="grow">
                    <div class="name">{{ Labels::ledgerEntry($entry->entry_type) }}</div>
                    <div class="meta">{{ $entry->recorded_at->format('d M Y, h:i A') }}</div>
                    @if ($entry->notes)<div class="meta text-truncate">{{ $entry->notes }}</div>@endif
                </div>
                <div class="val">
                    <div class="{{ $isDebit ? 'text-owed' : 'text-adv' }}">{{ $isDebit ? '+' : '−' }}{{ Money::format($isDebit ? $entry->debit : $entry->credit) }}</div>
                    <div class="meta">Bal {{ Money::format($entry->balance_after) }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $entries->links() }}</div>
@endif
@endsection
