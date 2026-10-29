@extends('layouts.app')

@section('title', 'Monthly Report')

@use('App\Support\Money')

@section('content')
@php
    $q = ['month' => $month->format('Y-m')];
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $canNext = $month->copy()->addMonth()->startOfMonth()->lte(now());
@endphp

<x-page-header title="Monthly Report" :sub="$month_label ?? $month->format('F Y')" :back="route('reports.index')" />

<div class="date-nav">
    <a class="nav-btn" href="{{ route('reports.monthly', ['month' => $prev]) }}" aria-label="Pichla mahina"><i class="bi bi-chevron-left"></i></a>
    <div class="date-pick"><i class="bi bi-calendar-month text-primary"></i><span>{{ $month_label ?? $month->format('F Y') }}</span></div>
    <a class="nav-btn {{ $canNext ? '' : 'disabled' }}" href="{{ $canNext ? route('reports.monthly', ['month' => $next]) : '#' }}" aria-label="Agla mahina"><i class="bi bi-chevron-right"></i></a>
</div>

{{-- Quick jump: recent months (Sep, Oct, …) --}}
@php
    $monthChips = collect(range(0, 5))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
@endphp
<div class="chip-row mb-3 no-print">
    @foreach ($monthChips as $chip)
        <a class="chip-link {{ $chip->format('Y-m') === $month->format('Y-m') ? 'is-active' : '' }}"
           href="{{ route('reports.monthly', ['month' => $chip->format('Y-m')]) }}">
            {{ $chip->format('M Y') }}
        </a>
    @endforeach
</div>

<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('reports.monthly', $q + ['format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
</div>

<div class="stat-grid">
    <div class="stat tone-indigo">
        <div class="stat-label">Sale Wale Din</div>
        <div class="stat-value">{{ $days_with_sales ?? 0 }}</div>
        <div class="stat-sub">{{ $days_recorded ?? 0 }} din record · {{ $month->daysInMonth }} din mahina</div>
    </div>
    <div class="stat tone-green"><div class="stat-label">Total Sale</div><div class="stat-value">{{ Money::format($totals['sales']) }}</div></div>
    <div class="stat tone-orange"><div class="stat-label">Total Maal (Bills)</div><div class="stat-value">{{ Money::format($totals['bills']) }}</div></div>
    <div class="stat tone-red"><div class="stat-label">Vendor Payments</div><div class="stat-value">{{ Money::format(\App\Support\Money::add($totals['vendor_payments'], $totals['paid_on_bill'])) }}</div></div>
</div>

<div class="tbl-wrap mb-3">
    <table class="tbl">
        <thead>
            <tr><th>Date</th><th class="num">Sale</th><th class="num">Bills</th><th class="num">Paid</th><th class="num">Vendor Pay</th><th class="num">Kharcha</th><th class="num">Rent Rakha</th><th class="num">Rent Diya</th><th class="num">Closing</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td><a href="{{ route('dashboard', ['date' => $r['date']->toDateString()]) }}">{{ $r['date']->format('d M') }}</a> @if ($r['status'] === 'closed')<i class="bi bi-lock-fill text-muted"></i>@endif</td>
                    <td class="num text-in">{{ Money::format($r['sales'], false) }}</td>
                    <td class="num">{{ Money::format($r['bills'], false) }}</td>
                    <td class="num">{{ Money::format($r['paid_on_bill'], false) }}</td>
                    <td class="num">{{ Money::format($r['vendor_payments'], false) }}</td>
                    <td class="num text-pay">{{ Money::format($r['expenses'], false) }}</td>
                    <td class="num">{{ Money::format($r['rent_reserved'], false) }}</td>
                    <td class="num">{{ Money::format($r['rent_paid'], false) }}</td>
                    <td class="num fw-bold">{{ $r['closing_cash'] !== null ? Money::format($r['closing_cash'], false) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Is mahine koi record nahi.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="num">{{ Money::format($totals['sales'], false) }}</td>
                    <td class="num">{{ Money::format($totals['bills'], false) }}</td>
                    <td class="num">{{ Money::format($totals['paid_on_bill'], false) }}</td>
                    <td class="num">{{ Money::format($totals['vendor_payments'], false) }}</td>
                    <td class="num">{{ Money::format($totals['expenses'], false) }}</td>
                    <td class="num">{{ Money::format($totals['rent_reserved'], false) }}</td>
                    <td class="num">{{ Money::format($totals['rent_paid'], false) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($segments->isNotEmpty())
    <div class="section-title"><span>Segment Wise Sale</span></div>
    <div class="list-card">
        @foreach ($segments as $seg)
            <div class="list-row"><div class="grow"><div class="name">{{ $seg['name'] }}</div><div class="meta">{{ $seg['count'] }} entries</div></div><div class="val text-in">{{ Money::format($seg['total']) }}</div></div>
        @endforeach
    </div>
@endif

@if ($vendors->isNotEmpty())
    <div class="section-title"><span>Vendor Wise</span></div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Vendor</th><th class="num">Bills</th><th class="num">Paid</th><th class="num">Balance Abhi</th></tr></thead>
            <tbody>
                @foreach ($vendors as $v)
                    <tr><td>{{ $v['name'] }}</td><td class="num">{{ Money::format($v['bills'], false) }}</td><td class="num">{{ Money::format($v['paid'], false) }}</td><td class="num fw-bold">{{ Money::format($v['balance'], false) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
