@extends('layouts.app')

@section('title', $vendor->name)

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $bal = bccomp((string) $vendor->current_balance, '0', 2);
@endphp

<x-page-header :title="$vendor->name" :sub="$vendor->phone ?: 'Vendor ledger'" :back="route('vendors.index')">
    <a href="{{ route('vendors.edit', $vendor) }}" class="back-btn" aria-label="Edit"><i class="bi bi-pencil-fill"></i></a>
</x-page-header>

<section class="hero-cash" style="{{ $bal > 0 ? 'background: radial-gradient(circle at 90% 0%, rgba(245,158,11,.5) 0, transparent 55%), linear-gradient(135deg, #b45309, #d97706);' : '' }}">
    <div class="label">
        <i class="bi bi-person-lines-fill"></i>
        {{ $bal > 0 ? 'Humein Dena Hai' : ($bal < 0 ? 'Advance Diya Hua' : 'Hisab Clear') }}
        @unless ($vendor->is_active)<span class="badge-soft red ms-1">Inactive</span>@endunless
    </div>
    <div class="amount">{{ Money::format(Money::abs($vendor->current_balance)) }}</div>
    <div class="meta"><span>Opening {{ Money::format($vendor->opening_balance) }}</span></div>
</section>

<div class="action-grid">
    <a href="{{ route('purchases.create', ['vendor' => $vendor->id]) }}" class="action-tile tile-blue" style="min-height:84px">
        <i class="bi bi-basket2-fill"></i><span>Maal Entry</span>
    </a>
    <a href="{{ route('payments.create', ['vendor' => $vendor->id]) }}" class="action-tile tile-red" style="min-height:84px">
        <i class="bi bi-send-fill"></i><span>Payment Karein</span>
    </a>
</div>

<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('reports.vendor', $vendor) }}" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-text"></i> Ledger</a>
    <a href="{{ route('reports.vendor', [$vendor, 'format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a href="{{ route('reports.vendor', [$vendor, 'format' => 'csv']) }}" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-filetype-csv"></i> CSV</a>
</div>

@if ($vendor->notes)
    <div class="card-x small"><i class="bi bi-sticky text-muted"></i> {{ $vendor->notes }}</div>
@endif

@if ($isAdmin)
    <div class="card-x" x-data="{ tab: '{{ $errors->has('new_balance') ? 'adjust' : ($errors->has('opening_balance') ? 'opening' : '') }}' }">
        <div class="card-title">Admin Tools</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn-xl btn-sm-x flex-fill" :class="tab === 'adjust' ? 'btn-blue' : 'btn-ghost'" @click="tab = tab === 'adjust' ? '' : 'adjust'">Balance Adjust</button>
            <button type="button" class="btn-xl btn-sm-x flex-fill" :class="tab === 'opening' ? 'btn-blue' : 'btn-ghost'" @click="tab = tab === 'opening' ? '' : 'opening'">Opening Balance</button>
        </div>

        <form method="POST" action="{{ route('vendors.adjust-balance', $vendor) }}" class="mt-3" x-show="tab === 'adjust'" x-cloak>
            @csrf
            <x-money-input name="new_balance" id="new_balance" label="Naya Balance" :signed="true" :compact="true" :value="Money::of($vendor->current_balance)"
                           hint="Positive = humein dena hai, negative = advance." />
            <div class="field">
                <label for="adjust_reason">Wajah (zaroori)</label>
                <input type="text" id="adjust_reason" name="reason" class="form-control-x" maxlength="500" required value="{{ old('reason') }}">
            </div>
            <button type="submit" class="btn-xl btn-sm-x btn-blue w-100">Adjust Karein</button>
        </form>

        <form method="POST" action="{{ route('vendors.opening-balance', $vendor) }}" class="mt-3" x-show="tab === 'opening'" x-cloak>
            @csrf
            <x-money-input name="opening_balance" id="opening_balance" label="Naya Opening Balance" :signed="true" :compact="true" :value="Money::of($vendor->opening_balance)"
                           hint="Current balance bhi isi farq se badal jayega." />
            <div class="field">
                <label for="opening_reason">Wajah (zaroori)</label>
                <input type="text" id="opening_reason" name="reason" class="form-control-x" maxlength="500" required>
            </div>
            <button type="submit" class="btn-xl btn-sm-x btn-blue w-100">Opening Badlein</button>
        </form>
    </div>
@endif

<div class="section-title"><span>Ledger</span><small>{{ $entries->total() }} entries</small></div>

@if ($entries->isEmpty())
    <div class="act-list"><div class="empty"><i class="bi bi-journal"></i>Abhi koi ledger entry nahi.</div></div>
@else
    <div class="list-card">
        @foreach ($entries as $entry)
            @php
                $isDebit = bccomp((string) $entry->debit, '0', 2) > 0;
                $purchase = $entry->reference_type === 'purchase' ? $purchases->get($entry->reference_id) : null;
                $linkActivity = null;
            @endphp
            <div class="list-row">
                @if ($purchase && $purchase->bill_image_path)
                    <a href="{{ route('invoices.show', $purchase) }}" target="_blank" rel="noopener" aria-label="Bill photo">
                        <img class="ledger-img" loading="lazy" src="{{ route('invoices.show', $purchase) }}" alt="Bill #{{ $purchase->id }}">
                    </a>
                @else
                    <span class="act-icon" style="{{ $isDebit ? 'color:var(--balance-orange);background:var(--balance-orange-soft)' : 'color:var(--brand-green);background:var(--brand-green-soft)' }}">
                        <i class="bi {{ $isDebit ? 'bi-basket2-fill' : 'bi-send-fill' }}"></i>
                    </span>
                @endif
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
