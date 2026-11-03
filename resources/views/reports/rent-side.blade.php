@extends('layouts.app')

@section('title', 'Rent Side History')

@use('App\Support\Money')

@section('content')
<x-page-header title="Rent Side History" sub="Monthly savings set aside and average" :back="route('reports.index')" />

<div class="stat-grid">
    <div class="stat tone-indigo">
        <div class="stat-label">{{ $current_label }} — Saved</div>
        <div class="stat-value">{{ Money::format($current_reserved) }}</div>
        <div class="stat-sub">Current month (open)</div>
    </div>
    <div class="stat tone-green">
        <div class="stat-label">Average / Month</div>
        <div class="stat-value">{{ Money::format($average_reserved) }}</div>
        <div class="stat-sub">{{ $months_with_side }} {{ $months_with_side === 1 ? 'month' : 'months' }} with savings</div>
    </div>
    <div class="stat tone-orange">
        <div class="stat-label">Total Saved</div>
        <div class="stat-value">{{ Money::format($total_reserved) }}</div>
        <div class="stat-sub">Live reserve: {{ Money::format($live_reserve) }}</div>
    </div>
</div>

<div class="tbl-wrap mb-3">
    <table class="tbl">
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Saved</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="{{ $r['is_current'] ? 'fw-bold' : '' }}">
                    <td>
                        <a href="{{ route('reports.monthly', ['month' => $r['ym']]) }}">{{ $r['label'] }}</a>
                    </td>
                    <td class="num text-in">{{ Money::format($r['reserved'], false) }}</td>
                    <td>
                        @if ($r['is_current'])
                            <span class="badge text-bg-primary">Open</span>
                        @else
                            <span class="badge text-bg-secondary">Closed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted py-4">No savings records yet.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total · Avg {{ Money::format($average_reserved, false) }}</td>
                    <td class="num">{{ Money::format($total_reserved, false) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

<a href="{{ route('sales.create', ['segment' => \App\Models\SaleSegment::where('is_rent_side', true)->value('id')]) }}" class="btn-xl btn-sm-x btn-blue w-100">
    <i class="bi bi-cash-coin"></i> Add Rent Side
</a>
@endsection
