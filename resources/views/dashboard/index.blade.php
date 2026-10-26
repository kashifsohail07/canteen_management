@extends('layouts.app')

@section('title', 'Home')

@use('App\Support\Money')

@section('content')
@php
    $prev = $date->copy()->subDay();
    $next = $date->copy()->addDay();
    $q = $entryDate ? ['date' => $entryDate] : [];
    $closed = $session?->isClosed() ?? false;
@endphp

<div class="date-nav">
    <a class="nav-btn" href="{{ route('dashboard', ['date' => $prev->toDateString()]) }}" aria-label="Previous day"><i class="bi bi-chevron-left"></i></a>
    <form method="GET" action="{{ route('dashboard') }}" class="date-pick m-0">
        <i class="bi bi-calendar3 text-primary"></i>
        <span>{{ $isToday ? 'Today · ' : '' }}{{ $date->format('D, d M Y') }}</span>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}"
               onchange="this.form.submit()" aria-label="Pick date">
    </form>
    <a class="nav-btn {{ $isToday ? 'disabled' : '' }}" href="{{ $isToday ? '#' : route('dashboard', ['date' => $next->toDateString()]) }}" aria-label="Next day" @if ($isToday) aria-disabled="true" tabindex="-1" @endif>
        <i class="bi bi-chevron-right"></i>
    </a>
</div>

@if ($closed)
    <div class="lock-banner">
        <i class="bi bi-lock-fill"></i>
        <div>
            <strong>This day is closed.</strong>
            <div class="small">New entries are locked. <a href="{{ route('closing.show', $q) }}">View closing summary</a></div>
        </div>
    </div>
@endif

@if (! $session)
    <div class="card-x">
        <div class="empty">
            <i class="bi bi-calendar-x"></i>
            <div class="fw-bold text-dark">No record for this day</div>
            <div class="small">Nothing was entered on {{ $date->format('d M Y') }}.</div>
            <a href="{{ route('dashboard') }}" class="btn-xl btn-sm-x btn-ghost mt-3">Back to today</a>
        </div>
    </div>
@else
    {{-- 1. Today sales snapshot (top) --}}
    <a class="dash-sales" href="{{ route('reports.daily', ['date' => $date->toDateString()]) }}">
        <div class="dash-sales-main">
            <div class="dash-sales-hero">
                <div class="dash-big" data-sales>{{ Money::format($summary['sales_today']) }}</div>
                <div class="dash-kicker">{{ $isToday ? 'Today’s sales' : 'Day’s sales' }}</div>
            </div>
            <div class="dash-sales-side">
                <div class="dash-side-val">{{ Money::format($summary['purchases_today']) }}</div>
                <div class="dash-side-label">Mall</div>
            </div>
        </div>
        <div class="dash-sales-row">
            <div>
                <strong>{{ Money::format(Money::mul($summary['sales_today'], '0.20')) }}</strong>
                <span>20% of sale</span>
            </div>
            <div>
                <strong>{{ Money::format($summary['month_sales_avg']) }}</strong>
                <span>{{ $summary['month_label'] }} avg</span>
            </div>
            <div>
                <strong>{{ Money::format($summary['month_sales_total']) }}</strong>
                <span>Month · {{ $summary['month_days_with_sales'] }}d</span>
            </div>
        </div>
    </a>

    {{-- 2. Daily cash --}}
    <section class="hero-cash" aria-label="Cash in hand">
        <div class="label"><i class="bi bi-wallet2"></i> {{ $isToday ? 'Cash in hand' : 'Closing cash' }}</div>
        <div class="amount" data-cash>{{ Money::format($cash) }}</div>
        <div class="meta">
            <span class="hero-pill"><i class="bi bi-sunrise"></i> Open {{ Money::format($session->opening_canteen_cash) }}</span>
            <span class="hero-pill"><i class="bi bi-tag"></i> {{ $session->day_type->label() }}</span>
            @if ($closed && $session->cash_difference !== null && bccomp((string) $session->cash_difference, '0', 2) !== 0)
                <span class="hero-pill warn"><i class="bi bi-exclamation-triangle"></i> Diff {{ Money::format($session->cash_difference) }}</span>
            @endif
        </div>
    </section>

    {{-- 3. Daily actions --}}
    <div class="dash-actions">
        <a href="{{ route('sales.create', $q) }}" class="dash-action da-sale {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-cash-coin"></i>
            <span>Add Sale</span>
        </a>
        <a href="{{ route('purchases.create', $q) }}" class="dash-action da-mall {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-basket2-fill"></i>
            <span>Add Mall</span>
        </a>
        <a href="{{ route('payments.create', $q) }}" class="dash-action da-pay {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-send-fill"></i>
            <span>Pay Vendor</span>
        </a>
    </div>

    <div class="dash-tools">
        <a href="{{ route('closing.show', $q) }}"><i class="bi bi-lock"></i> Close Day</a>
        <a href="{{ route('reports.index') }}"><i class="bi bi-bar-chart"></i> Reports</a>
        <a href="{{ route('vendors.index') }}"><i class="bi bi-people"></i> Vendors</a>
        <a href="{{ route('customers.index') }}"><i class="bi bi-person-badge"></i> Customers</a>
    </div>

    {{-- 4. Overview (secondary) --}}
    <div class="dash-overview">
        <a class="dash-mini dm-owed" href="{{ route('payments.create', $q) }}">
            <span class="dash-kicker">Vendors ko dena hai</span>
            <strong data-vendors>{{ Money::format($summary['vendors_owed']) }}</strong>
            <small>{{ $vendorCount }} {{ $vendorCount === 1 ? 'vendor' : 'vendors' }}</small>
        </a>
        <a class="dash-mini dm-owed" href="{{ route('customers.index') }}">
            <span class="dash-kicker">Customers se lena hai</span>
            <strong data-customers>{{ Money::format($summary['customers_owed'] ?? 0) }}</strong>
            <small>{{ $customerCount }} {{ $customerCount === 1 ? 'customer' : 'customers' }}</small>
        </a>
        <a class="dash-mini dm-rent" href="{{ route('rent.create', $q) }}">
            <span class="dash-kicker">Rent side</span>
            <strong data-rent>{{ Money::format($summary['rent_reserve']) }}</strong>
            <small>Paid {{ Money::format($summary['rent']['paid_total']) }} / {{ Money::format($summary['rent']['target']) }}</small>
        </a>
    </div>

    @if ($segments && $segments->isNotEmpty())
        <div class="section-title section-tight">
            <span>Sales by break</span>
        </div>
        <div class="seg-strip" aria-label="Sales by break">
            @foreach ($segments as $seg)
                <span class="seg-chip">
                    <span class="seg-name">{{ $seg['name'] }}</span>
                    <b>{{ Money::format($seg['total']) }}</b>
                </span>
            @endforeach
        </div>
    @endif

    @if ($vendorCount === 0)
        <div class="card-x text-center">
            <i class="bi bi-people fs-1 text-primary"></i>
            <div class="fw-bold mt-1">No vendors yet</div>
            <div class="small text-muted mb-3">Add vendors before recording mall.</div>
            <a href="{{ route('vendors.create') }}" class="btn-xl btn-sm-x btn-blue">Add Vendor</a>
        </div>
    @endif

    <div class="section-title">
        <span>{{ $isToday ? 'Today’s activity' : 'Day’s activity' }}</span>
        <small data-activity-total>{{ $activity->total() }} {{ $activity->total() === 1 ? 'entry' : 'entries' }}</small>
    </div>

    @if ($activity->total() === 0)
        <div class="act-list">
            <div class="empty">
                <i class="bi bi-inbox"></i>
                <div class="fw-bold text-dark">No entries yet</div>
                <div class="small">Sales and mall will show up here.</div>
            </div>
        </div>
    @else
        <div x-data="activityFeed(@js(['url' => route('dashboard.activity', [], false), 'date' => $date->toDateString(), 'next' => $activity->hasMorePages() ? 2 : null]))">
            <div class="act-list" x-ref="list">
                @foreach ($activity as $item)
                    <x-activity-item :activity="$item" :day-open="$session?->isOpen() ?? false" />
                @endforeach
            </div>
            <div class="text-center mt-3" x-show="next" x-cloak>
                <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="more()" :class="{ disabled: loading }">
                    <span x-show="!loading"><i class="bi bi-arrow-down-circle me-1"></i> Load more</span>
                    <span x-show="loading">Loading…</span>
                </button>
                <div class="field-error mt-2" x-show="failed" x-cloak>Couldn’t load. Try again.</div>
            </div>
        </div>
    @endif
@endif
@endsection
