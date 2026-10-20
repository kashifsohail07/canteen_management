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

{{-- date navigation --}}
<div class="date-nav">
    <a class="nav-btn" href="{{ route('dashboard', ['date' => $prev->toDateString()]) }}" aria-label="Pichla din"><i class="bi bi-chevron-left"></i></a>
    <form method="GET" action="{{ route('dashboard') }}" class="date-pick m-0">
        <i class="bi bi-calendar3 text-primary"></i>
        <span>{{ $isToday ? 'Aaj · ' : '' }}{{ $date->format('D, d M Y') }}</span>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}"
               onchange="this.form.submit()" aria-label="Date chunein">
    </form>
    <a class="nav-btn {{ $isToday ? 'disabled' : '' }}" href="{{ $isToday ? '#' : route('dashboard', ['date' => $next->toDateString()]) }}" aria-label="Agla din" @if ($isToday) aria-disabled="true" tabindex="-1" @endif>
        <i class="bi bi-chevron-right"></i>
    </a>
</div>

@if ($closed)
    <div class="lock-banner">
        <i class="bi bi-lock-fill"></i>
        <div>
            <strong>Yeh din close ho chuka hai.</strong>
            <div class="small">Naye entries band hain. <a href="{{ route('closing.show', $q) }}">Closing summary dekhein</a></div>
        </div>
    </div>
@endif

@if (! $session)
    <div class="card-x">
        <div class="empty">
            <i class="bi bi-calendar-x"></i>
            <div class="fw-bold text-dark">Is din ka koi record nahi</div>
            <div class="small">{{ $date->format('d M Y') }} ko koi entry nahi hui.</div>
            <a href="{{ route('dashboard') }}" class="btn-xl btn-sm-x btn-ghost mt-3">Aaj par wapis jayein</a>
        </div>
    </div>
@else
    {{-- hero cash --}}
    <section class="hero-cash" aria-label="Available cash">
        <div class="label"><i class="bi bi-cash-stack"></i> {{ $isToday ? 'Available Cash (Cash in Hand)' : 'Din Ke Aakhir Ka Cash' }}</div>
        <div class="amount" data-cash>{{ Money::format($cash) }}</div>
        <div class="meta">
            <span><i class="bi bi-sunrise"></i> Opening {{ Money::format($session->opening_canteen_cash) }}</span>
            <span><i class="bi bi-tag"></i> {{ $session->day_type->label() }}</span>
            @if ($closed && $session->cash_difference !== null && bccomp((string) $session->cash_difference, '0', 2) !== 0)
                <span><i class="bi bi-exclamation-triangle"></i> Farq {{ Money::format($session->cash_difference) }}</span>
            @endif
        </div>
    </section>

    {{-- compact cards --}}
    <div class="stat-grid">
        <a class="stat tone-green" href="{{ route('reports.daily', ['date' => $date->toDateString()]) }}">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-label">{{ $isToday ? 'Aaj Ki Sale' : 'Is Din Ki Sale' }}</div>
            <div class="stat-value" data-sales>{{ Money::format($summary['sales_today']) }}</div>
            <div class="stat-sub">Maal {{ Money::format($summary['purchases_today']) }}</div>
        </a>
        <a class="stat tone-orange" href="{{ route('vendors.index', ['filter' => 'owed']) }}">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <div class="stat-label">Vendor Outstanding</div>
            <div class="stat-value" data-vendors>{{ Money::format($summary['vendors_owed']) }}</div>
            <div class="stat-sub">{{ $vendorCount }} vendors</div>
        </a>
        <a class="stat tone-blue" href="{{ route('rent.create', $q) }}">
            <div class="stat-icon"><i class="bi bi-house-lock"></i></div>
            <div class="stat-label">Rent Side</div>
            <div class="stat-value" data-rent>{{ Money::format($summary['rent_reserve']) }}</div>
            <div class="stat-sub">Diya {{ Money::format($summary['rent']['paid_total']) }} / {{ Money::format($summary['rent']['target']) }}</div>
        </a>
    </div>

    @if ($segments && $segments->isNotEmpty())
        <div class="seg-strip" aria-label="Segment wise sale">
            @foreach ($segments as $seg)
                <span class="seg-chip">{{ $seg['name'] }} <b>{{ Money::format($seg['total']) }}</b></span>
            @endforeach
        </div>
    @endif

    {{-- quick actions --}}
    <div class="action-grid">
        <a href="{{ route('sales.create', $q) }}" class="action-tile tile-green {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-cash-coin"></i>
            <span>Add Sale<small>Bikri likhein</small></span>
        </a>
        <a href="{{ route('purchases.create', $q) }}" class="action-tile tile-blue {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-basket2-fill"></i>
            <span>Add Mall<small>Bill amount</small></span>
        </a>
        <a href="{{ route('payments.create', $q) }}" class="action-tile tile-red {{ $canEnter ? '' : 'is-disabled' }}" @unless ($canEnter) aria-disabled="true" tabindex="-1" @endunless>
            <i class="bi bi-send-fill"></i>
            <span>All Vendors<small>Mall + payment</small></span>
        </a>
    </div>

    <div class="chip-row">
        <a class="chip-link" href="{{ route('closing.show', $q) }}"><i class="bi bi-lock"></i>Din Close</a>
        <a class="chip-link" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart"></i>Reports</a>
    </div>

    @if ($vendorCount === 0)
        <div class="card-x text-center">
            <i class="bi bi-people fs-1 text-primary"></i>
            <div class="fw-bold mt-1">Abhi koi vendor nahi hai</div>
            <div class="small text-muted mb-3">Maal entry ke liye pehle vendors add karein.</div>
            <a href="{{ route('vendors.create') }}" class="btn-xl btn-sm-x btn-blue">Vendor Banayein</a>
        </div>
    @endif

    {{-- activity --}}
    <div class="section-title">
        <span>{{ $isToday ? 'Aaj Ki Activity' : 'Is Din Ki Activity' }}</span>
        <small data-activity-total>{{ $activity->total() }} entries</small>
    </div>

    @if ($activity->total() === 0)
        <div class="act-list">
            <div class="empty">
                <i class="bi bi-inbox"></i>
                <div class="fw-bold text-dark">Abhi koi entry nahi</div>
                <div class="small">Sale ya maal entry karein, yahan nazar aayegi.</div>
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
                    <span x-show="!loading"><i class="bi bi-arrow-down-circle me-1"></i> Aur Dikhayein</span>
                    <span x-show="loading">Load ho raha hai…</span>
                </button>
                <div class="field-error mt-2" x-show="failed" x-cloak>Load nahi hua. Dobara try karein.</div>
            </div>
        </div>
    @endif
@endif
@endsection
