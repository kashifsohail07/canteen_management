@extends('layouts.app')

@section('title', 'Users')

@section('content')
@php
    $filterQ = array_filter(['q' => $q ?: null]);
@endphp

<x-page-header title="Users" sub="Super User — accounts manage" :back="route('dashboard')">
    <a href="{{ route('users.create') }}" class="btn-xl btn-sm-x btn-blue"><i class="bi bi-person-plus"></i> Add</a>
</x-page-header>

<div class="role-guide mb-3">
    @foreach (\App\Enums\UserRole::cases() as $role)
        <div class="role-guide-card">
            <div class="role-guide-head"><strong>{{ $role->label() }}</strong></div>
            <p class="role-guide-desc mb-0">{{ $role->description() }}</p>
        </div>
    @endforeach
</div>

<form method="GET" action="{{ route('users.index') }}" class="search-bar">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="form-control-x" placeholder="Name ya email…" autocomplete="off">
    @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
</form>

<div class="filter-tabs">
    <a href="{{ route('users.index', $filterQ) }}" class="{{ $filter === 'all' ? 'on' : '' }}">All ({{ $counts['all'] }})</a>
    <a href="{{ route('users.index', array_merge($filterQ, ['filter' => 'active'])) }}" class="{{ $filter === 'active' ? 'on' : '' }}">Active</a>
    <a href="{{ route('users.index', array_merge($filterQ, ['filter' => 'inactive'])) }}" class="{{ $filter === 'inactive' ? 'on' : '' }}">Inactive</a>
    <a href="{{ route('users.index', array_merge($filterQ, ['filter' => 'admin'])) }}" class="{{ $filter === 'admin' ? 'on' : '' }}">Admin</a>
    <a href="{{ route('users.index', array_merge($filterQ, ['filter' => 'staff'])) }}" class="{{ $filter === 'staff' ? 'on' : '' }}">Staff</a>
</div>

@if ($users->isEmpty())
    <div class="act-list">
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="fw-bold text-dark">No users found</div>
            <a href="{{ route('users.create') }}" class="btn-xl btn-sm-x btn-blue mt-3">Add User</a>
        </div>
    </div>
@else
    <div class="list-card">
        @foreach ($users as $u)
            <a href="{{ route('users.edit', $u) }}" class="list-row">
                <span class="vendor-avatar">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                <div class="grow">
                    <div class="name text-truncate">
                        {{ $u->name }}
                        @if ($u->is(auth()->user()))
                            <span class="badge-soft blue ms-1">You</span>
                        @endif
                        @unless ($u->is_active)
                            <span class="badge-soft red ms-1">Inactive</span>
                        @endunless
                    </div>
                    <div class="meta text-truncate">{{ $u->email }}</div>
                </div>
                <div class="val text-end">
                    <div class="meta fw-bold" style="color:var(--ink-soft)">{{ $u->role->label() }}</div>
                    <div class="meta">{{ $u->is_active ? 'Active' : 'Off' }}</div>
                </div>
            </a>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $users->links() }}</div>
@endif
@endsection
