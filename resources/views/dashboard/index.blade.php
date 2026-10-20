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

    <div class="stat-grid">
        <a class="stat tone-green" href="{{ route('reports.daily', ['date' => $date->toDateString()]) }}">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-label">{{ $isToday ? 'Today’s sales' : 'Day’s sales' }}</div>
            <div class="stat-value" data-sales>{{ Money::format($summary['sales_today']) }}</div>
            <div class="stat-sub">Mall {{ Money::format($summary['purchases_today']) }}</div>
        </a>
        <a class="stat tone-orange" href="{{ route('payments.create', $q) }}">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-label">Vendors ko dena hai</div>
            <div class="stat-value" data-vendors>{{ Money::format($summary['vendors_owed']) }}</div>
            <div class="stat-sub">{{ $vendorCount }} {{ $vendorCount === 1 ? 'vendor' : 'vendors' }}</div>
        </a>
        <a class="stat tone-blue stat-wide" href="{{ route('rent.create', $q) }}">
            <div class="stat-wide-inner">
                <div class="stat-icon"><i class="bi bi-house-lock"></i></div>
                <div class="grow">
                    <div class="stat-label">Rent side</div>
                    <div class="stat-value" data-rent>{{ Money::format($summary['rent_reserve']) }}</div>
                </div>
                <div class="stat-sub text-end">
                    Paid {{ Money::format($summary['rent']['paid_total']) }}
                    <div class="text-muted">of {{ Money::format($summary['rent']['target']) }}</div>
                </div>
            </div>
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

    <div class="section-title section-tight">
        <span>Quick actions</span>
    </div>
    <div class="action-grid action-grid-3">
        <a href="{{ route('sales.create', $q) }}" class="action-tile tile-green {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-cash-coin"></i>
            <span>Add Sale</span>
        </a>
        <a href="{{ route('purchases.create', $q) }}" class="action-tile tile-blue {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-basket2-fill"></i>
            <span>Add Mall</span>
        </a>
        <a href="{{ route('payments.create', $q) }}" class="action-tile tile-red {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-send-fill"></i>
            <span>Vendors</span>
        </a>
    </div>

    <div class="chip-row chip-row-2">
        <a class="chip-link" href="{{ route('closing.show', $q) }}"><i class="bi bi-lock"></i>Close Day</a>
        <a class="chip-link" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart"></i>Reports</a>
    </div>

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
                    <x-activity-item :activity="$item" />
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
