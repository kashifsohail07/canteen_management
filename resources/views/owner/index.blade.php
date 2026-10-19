@extends('layouts.app')

@section('title', 'Owner')

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
<x-page-header title="Owner Ledger" sub="Owner ke paisay ka hisab" :back="route('dashboard')" />

<section class="hero-cash" style="background: radial-gradient(circle at 90% 0%, rgba(14,159,110,.5) 0, transparent 55%), linear-gradient(135deg, #3730a3, #5850ec);">
    <div class="label"><i class="bi bi-person-badge-fill"></i> Owner Recoverable (Owner Ka Lena)</div>
    <div class="amount">{{ Money::format($recoverable) }}</div>
    <div class="meta"><span><i class="bi bi-cash-stack"></i> Gallay mein {{ Money::format($cashInHand) }}</span></div>
</section>

<div class="action-grid">
    <a href="{{ route('owner.deposit') }}" class="action-tile tile-green">
        <i class="bi bi-box-arrow-in-down"></i>
        <span>Owner Ne Cash Diya<small>Deposit</small></span>
    </a>
    <a href="{{ route('owner.repayment') }}" class="action-tile tile-red">
        <i class="bi bi-box-arrow-up"></i>
        <span>Owner Ko Wapis<small>Repayment</small></span>
    </a>
</div>

<div class="section-title"><span>Ledger</span><small>{{ $transactions->total() }} entries</small></div>

@if ($transactions->isEmpty())
    <div class="act-list"><div class="empty"><i class="bi bi-inbox"></i>Abhi koi owner entry nahi.</div></div>
@else
    <div class="list-card">
        @foreach ($transactions as $tx)
            @php($up = $tx->direction === 'increase_recoverable')
            <div class="list-row">
                <span class="act-icon {{ $up ? 'act-green' : 'act-red' }}" style="{{ $up ? 'color:var(--brand-green);background:var(--brand-green-soft)' : 'color:var(--pay-red);background:var(--pay-red-soft)' }}">
                    <i class="bi {{ $up ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
                </span>
                <div class="grow">
                    <div class="name">{{ Labels::ownerTransaction($tx->type) }}</div>
                    <div class="meta">{{ $tx->recorded_at->format('d M, h:i A') }}@if ($tx->notes) · {{ \Illuminate\Support\Str::limit($tx->notes, 40) }}@endif</div>
                </div>
                <div class="val">
                    <div class="{{ $up ? 'text-in' : 'text-pay' }}">{{ $up ? '+' : '−' }}{{ Money::format($tx->amount) }}</div>
                    <div class="meta">Baqaya {{ Money::format($tx->balance_after) }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $transactions->links() }}</div>
@endif
@endsection
