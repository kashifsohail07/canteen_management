@extends('layouts.app')

@section('title', 'Vendors')

@use('App\Support\Money')

@section('content')
<x-page-header title="Vendors" sub="Sab vendors aur unka balance" :back="route('dashboard')">
    <a href="{{ route('vendors.create') }}" class="btn-xl btn-sm-x btn-blue"><i class="bi bi-plus-lg"></i> Naya</a>
</x-page-header>

<div class="balance-box">
    <span>Vendors ko total dena hai</span>
    <b class="text-owed">{{ Money::format($totalOwed) }}</b>
</div>

<form method="GET" action="{{ route('vendors.index') }}" class="search-bar">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="form-control-x" placeholder="Vendor dhoondein…" autocomplete="off">
    @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
</form>

<div class="filter-tabs">
    <a href="{{ route('vendors.index', array_filter(['q' => $q])) }}" class="{{ $filter === 'all' ? 'on' : '' }}">Sab</a>
    <a href="{{ route('vendors.index', array_filter(['q' => $q, 'filter' => 'owed'])) }}" class="{{ $filter === 'owed' ? 'on' : '' }}">Dena Hai</a>
    <a href="{{ route('vendors.index', array_filter(['q' => $q, 'filter' => 'advance'])) }}" class="{{ $filter === 'advance' ? 'on' : '' }}">Advance</a>
</div>

@if ($vendors->isEmpty())
    <div class="act-list">
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="fw-bold text-dark">Koi vendor nahi mila</div>
            <a href="{{ route('vendors.create') }}" class="btn-xl btn-sm-x btn-blue mt-3">Vendor Banayein</a>
        </div>
    </div>
@else
    <div class="list-card">
        @foreach ($vendors as $vendor)
            @php($owed = bccomp((string) $vendor->current_balance, '0', 2))
            <a href="{{ route('vendors.show', $vendor) }}" class="list-row">
                <span class="vendor-avatar">{{ mb_strtoupper(mb_substr($vendor->name, 0, 1)) }}</span>
                <div class="grow">
                    <div class="name text-truncate">{{ $vendor->name }}
                        @unless ($vendor->is_active)<span class="badge-soft red ms-1">Inactive</span>@endunless
                    </div>
                    <div class="meta">{{ $vendor->phone ?: 'Phone nahi' }}</div>
                </div>
                <div class="val {{ $owed > 0 ? 'text-owed' : ($owed < 0 ? 'text-adv' : 'text-muted') }}">
                    {{ Money::format(\App\Support\Money::abs($vendor->current_balance)) }}
                    <div class="meta">{{ $owed > 0 ? 'dena hai' : ($owed < 0 ? 'advance' : 'clear') }}</div>
                </div>
            </a>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $vendors->links() }}</div>
@endif
@endsection
