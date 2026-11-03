@extends('layouts.app')

@section('title', 'Customers')

@use('App\Support\Money')

@section('content')
<x-page-header title="Customers" sub="Udhaar hisaab — lena hai" :back="route('dashboard')">
    <a href="{{ route('customers.create') }}" class="btn-xl btn-sm-x btn-blue"><i class="bi bi-plus-lg"></i> Add</a>
</x-page-header>

<section class="owed-card" aria-label="Total lena hai">
    <div class="owed-card-icon"><i class="bi bi-wallet2"></i></div>
    <div class="owed-card-body">
        <span class="owed-card-label">Total lena hai</span>
        <strong class="owed-card-amount">{{ Money::format($totalOwed) }}</strong>
        <small class="owed-card-meta">
            {{ $owedCount }} {{ $owedCount === 1 ? 'customer' : 'customers' }} pe baqaya
        </small>
    </div>
</section>

<form method="GET" action="{{ route('customers.index') }}" class="search-bar">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="form-control-x" placeholder="Search customer…" autocomplete="off">
    @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
</form>

<div class="filter-tabs">
    <a href="{{ route('customers.index', array_filter(['q' => $q])) }}" class="{{ $filter === 'all' ? 'on' : '' }}">All</a>
    <a href="{{ route('customers.index', array_filter(['q' => $q, 'filter' => 'owed'])) }}" class="{{ $filter === 'owed' ? 'on' : '' }}">Lena Hai</a>
    <a href="{{ route('customers.index', array_filter(['q' => $q, 'filter' => 'advance'])) }}" class="{{ $filter === 'advance' ? 'on' : '' }}">Advance</a>
</div>

@if ($customers->isEmpty())
    <div class="act-list">
        <div class="empty">
            <i class="bi bi-person-badge"></i>
            <div class="fw-bold text-dark">No customers yet</div>
            <div class="small text-muted mb-2">Jo udhaar pe lete hain unhein yahan add karo.</div>
            <a href="{{ route('customers.create') }}" class="btn-xl btn-sm-x btn-blue mt-2">Add Customer</a>
        </div>
    </div>
@else
    <div class="list-card">
        @foreach ($customers as $customer)
            @php($owed = bccomp((string) $customer->current_balance, '0', 2))
            <a href="{{ route('customers.show', $customer) }}" class="list-row">
                <span class="vendor-avatar">{{ mb_strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                <div class="grow">
                    <div class="name text-truncate">{{ $customer->name }}
                        @unless ($customer->is_active)<span class="badge-soft red ms-1">Inactive</span>@endunless
                    </div>
                    <div class="meta">Opening {{ Money::format($customer->opening_balance) }}</div>
                </div>
                <div class="val {{ $owed > 0 ? 'text-owed' : ($owed < 0 ? 'text-adv' : 'text-muted') }}">
                    {{ Money::format(\App\Support\Money::abs($customer->current_balance)) }}
                    <div class="meta">{{ $owed > 0 ? 'lena hai' : ($owed < 0 ? 'advance' : 'clear') }}</div>
                </div>
            </a>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $customers->links() }}</div>
@endif
@endsection
