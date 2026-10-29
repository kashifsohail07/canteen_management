@extends('layouts.app')

@section('title', 'Daily Report')

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php $s = $summary; $q = ['date' => $date->toDateString()]; @endphp

<x-page-header title="Daily Report" :sub="$date->format('l, d M Y')" :back="route('reports.index')">
    <span class="badge-soft {{ $session->isClosed() ? 'red' : 'green' }}">{{ $session->isClosed() ? 'Closed' : 'Open' }}</span>
</x-page-header>

<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('reports.daily', $q + ['format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
</div>

<div class="card-x">
    <div class="card-title">Cash</div>
    <div class="kv"><span>Opening</span><span>{{ Money::format($s['opening_canteen_cash']) }}</span></div>
    <div class="kv"><span>Total aamad</span><span class="text-in">+{{ Money::format($s['total_cash_in']) }}</span></div>
    <div class="kv"><span>Total kharch</span><span class="text-pay">−{{ Money::format($s['total_cash_out']) }}</span></div>
    <div class="kv total"><span>Expected closing</span><span>{{ Money::format($s['expected_closing_cash']) }}</span></div>
    @if ($s['actual_counted_cash'] !== null)
        <div class="kv"><span>Ginti ka cash</span><span>{{ Money::format($s['actual_counted_cash']) }}</span></div>
        <div class="kv"><span>Farq</span><span>{{ Money::format($s['cash_difference']) }}</span></div>
        @if ($s['difference_reason'])<div class="kv"><span>Wajah</span><span>{{ $s['difference_reason'] }}</span></div>@endif
    @endif
</div>

<div class="card-x">
    <div class="card-title">Khulasa</div>
    <div class="kv"><span>Sale</span><span class="text-in">{{ Money::format($s['sales_total']) }}</span></div>
    @foreach ($segments as $seg)
        <div class="kv"><span>&nbsp;&nbsp;· {{ $seg['name'] }}</span><span>{{ Money::format($seg['total']) }}</span></div>
    @endforeach
    <div class="kv"><span>Maal (bills)</span><span>{{ Money::format($s['purchases_total']) }}</span></div>
    <div class="kv"><span>Vendor payments</span><span>{{ Money::format($s['vendor_payments_total']) }}</span></div>
    <div class="kv"><span>Kharcha</span><span class="text-pay">{{ Money::format($s['expenses_total']) }}</span></div>
    <div class="kv"><span>Rent rakha / diya</span><span>{{ Money::format($s['rent_reserved']) }} / {{ Money::format($s['rent_paid']) }}</span></div>
    <div class="kv"><span>Vendors outstanding</span><span class="text-owed">{{ Money::format($s['vendors_outstanding']) }}</span></div>
</div>

@if ($purchases->isNotEmpty())
    <div class="section-title"><span>Maal / Bills</span><small>{{ $purchases->count() }}</small></div>
    <div class="list-card">
        @foreach ($purchases as $p)
            <div class="list-row">
                <div class="grow"><div class="name">{{ $p->vendor->name }}</div><div class="meta">{{ $p->recorded_at->format('h:i A') }} · Paid {{ Money::format($p->paid_amount) }}</div></div>
                <div class="val">{{ Money::format($p->bill_amount) }}</div>
            </div>
        @endforeach
    </div>
@endif

@if ($payments->isNotEmpty())
    <div class="section-title"><span>Vendor Payments</span><small>{{ $payments->count() }}</small></div>
    <div class="list-card">
        @foreach ($payments as $p)
            <div class="list-row">
                <div class="grow"><div class="name">{{ $p->vendor->name }}</div><div class="meta">{{ $p->recorded_at->format('h:i A') }} · {{ Labels::source($p->payment_source) }}</div></div>
                <div class="val text-pay">{{ Money::format($p->amount) }}</div>
            </div>
        @endforeach
    </div>
@endif

@if ($expenses->isNotEmpty())
    <div class="section-title"><span>Kharcha</span><small>{{ $expenses->count() }}</small></div>
    <div class="list-card">
        @foreach ($expenses as $e)
            <div class="list-row">
                <div class="grow"><div class="name">{{ $e->category?->name ?? 'Kharcha' }}</div><div class="meta">{{ $e->recorded_at->format('h:i A') }} · {{ Labels::source($e->payment_source) }}@if ($e->notes) · {{ $e->notes }}@endif</div></div>
                <div class="val text-pay">{{ Money::format($e->amount) }}</div>
            </div>
        @endforeach
    </div>
@endif

<div class="section-title"><span>Poori Activity</span><small>{{ $activity->count() }}</small></div>
<div class="act-list">
    @forelse ($activity as $item)
        <x-activity-item :activity="$item" />
    @empty
        <div class="empty"><i class="bi bi-inbox"></i>Koi entry nahi.</div>
    @endforelse
</div>
@endsection
