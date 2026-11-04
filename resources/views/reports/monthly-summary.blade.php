@extends('layouts.app')

@section('title', 'Monthly Summary')

@use('App\Support\Money')

@section('content')
@php
    $ym = $month->format('Y-m');
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $canNext = $month->copy()->addMonth()->startOfMonth()->lte(now());
@endphp

<x-page-header title="Monthly Summary" :sub="$month_label ?? $month->format('F Y')" :back="route('reports.index')" />

<div class="date-nav">
    <a class="nav-btn" href="{{ route('reports.monthly-summary', ['month' => $prev]) }}" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
    <div class="date-pick"><i class="bi bi-calendar-month text-primary"></i><span>{{ $month_label ?? $month->format('F Y') }}</span></div>
    <a class="nav-btn {{ $canNext ? '' : 'disabled' }}" href="{{ $canNext ? route('reports.monthly-summary', ['month' => $next]) : '#' }}" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
</div>

@php
    $monthChips = collect(range(0, 5))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
@endphp
<div class="chip-row mb-3">
    @foreach ($monthChips as $chip)
        <a class="chip-link {{ $chip->format('Y-m') === $ym ? 'is-active' : '' }}"
           href="{{ route('reports.monthly-summary', ['month' => $chip->format('Y-m')]) }}">
            {{ $chip->format('M Y') }}
        </a>
    @endforeach
</div>

{{-- Headline numbers --}}
<div class="stat-grid">
    <div class="stat tone-green">
        <div class="stat-label">Total Sale</div>
        <div class="stat-value">{{ Money::format($totals['sales']) }}</div>
        <div class="stat-sub">{{ $days_with_sales }} selling days</div>
    </div>
    <div class="stat tone-orange">
        <div class="stat-label">Total Mall (Bills)</div>
        <div class="stat-value">{{ Money::format($totals['bills']) }}</div>
        <div class="stat-sub">Paid to vendors {{ Money::format($paid_to_vendors) }}</div>
    </div>
    <div class="stat tone-indigo">
        <div class="stat-label">Available Cash</div>
        <div class="stat-value">{{ Money::format($available_cash) }}</div>
        <div class="stat-sub">{{ $is_current_month ? 'Live drawer now' : 'Month-end closing' }}</div>
    </div>
    <div class="stat tone-blue">
        <div class="stat-label">Rent Saving</div>
        <div class="stat-value">{{ Money::format($rent_saved) }}</div>
        <div class="stat-sub">Live reserve {{ Money::format($live_reserve) }}</div>
    </div>
</div>

{{-- Item qty (samosa / roll etc. from vendor mall) --}}
<div class="section-title"><span>Items purchased</span><small>Mall qty this month</small></div>
@if ($item_qty === [])
    <div class="card-x"><div class="small text-muted">No itemized mall qty recorded this month.</div></div>
@else
    <div class="list-card mb-3">
        @foreach ($item_qty as $item)
            <div class="list-row">
                <div class="grow">
                    <div class="name">{{ $item['name'] }}</div>
                    <div class="meta">{{ $item['unit'] }}</div>
                </div>
                <div class="val text-in">{{ $item['qty'] }}</div>
            </div>
        @endforeach
    </div>
@endif

{{-- Top purchaser --}}
<div class="section-title"><span>Top purchaser</span></div>
@if ($top_purchaser)
    <div class="card-x mb-3">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <div>
                <div class="fw-bold fs-5">{{ $top_purchaser['name'] }}</div>
                <div class="small text-muted">Highest mall bills this month</div>
            </div>
            <div class="text-end">
                <div class="fw-bold text-owed">{{ Money::format($top_purchaser['bills']) }}</div>
                <div class="small text-muted">Paid {{ Money::format($top_purchaser['paid']) }}</div>
            </div>
        </div>
    </div>
@else
    <div class="card-x mb-3"><div class="small text-muted">No mall bills this month.</div></div>
@endif

{{-- Vendors payable --}}
<div class="section-title">
    <span>Vendors payable</span>
    <small>{{ $vendors_payable_count }} · {{ Money::format($vendors_payable_total) }}</small>
</div>
@if ($vendors_payable->isEmpty())
    <div class="card-x mb-3"><div class="small text-muted">No vendor balances outstanding.</div></div>
@else
    <div class="list-card mb-3">
        @foreach ($vendors_payable as $v)
            <a class="list-row" href="{{ route('vendors.show', $v['id']) }}">
                <div class="grow">
                    <div class="name">{{ $v['name'] }}</div>
                </div>
                <div class="val text-owed">{{ Money::format($v['balance']) }}</div>
            </a>
        @endforeach
    </div>
@endif

{{-- Sale by break --}}
@if ($segments->isNotEmpty())
    <div class="section-title"><span>Sale by break</span></div>
    <div class="list-card mb-3">
        @foreach ($segments as $seg)
            <div class="list-row">
                <div class="grow">
                    <div class="name">{{ $seg['name'] }}</div>
                    <div class="meta">{{ $seg['count'] }} entries</div>
                </div>
                <div class="val text-in">{{ Money::format($seg['total']) }}</div>
            </div>
        @endforeach
    </div>
@endif

{{-- All vendors this month by bill --}}
@if ($vendors->isNotEmpty())
    <div class="section-title"><span>Vendors this month</span></div>
    <div class="tbl-wrap mb-3">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th class="num">Bills</th>
                    <th class="num">Paid</th>
                    <th class="num">Balance now</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vendors->sortByDesc(fn ($v) => (float) $v['bills']) as $v)
                    <tr>
                        <td>{{ $v['name'] }}</td>
                        <td class="num">{{ Money::format($v['bills'], false) }}</td>
                        <td class="num">{{ Money::format($v['paid'], false) }}</td>
                        <td class="num fw-bold">{{ Money::format($v['balance'], false) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="d-flex gap-2">
    <a href="{{ route('reports.monthly', ['month' => $ym]) }}" class="btn-xl btn-sm-x btn-ghost flex-fill">
        <i class="bi bi-calendar3"></i> Day-by-day
    </a>
    <a href="{{ route('reports.rent-side') }}" class="btn-xl btn-sm-x btn-outline-x flex-fill">
        <i class="bi bi-house-lock-fill"></i> Rent history
    </a>
</div>
@endsection
