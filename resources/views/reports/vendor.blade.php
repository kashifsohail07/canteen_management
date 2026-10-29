@extends('layouts.app')

@section('title', $vendor->name.' Ledger')

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $q = array_filter(['from' => $from?->toDateString(), 'to' => $to?->toDateString()]);
@endphp

<x-page-header :title="$vendor->name" :sub="'Ledger · '.($from ? $from->format('d M Y') : 'shuru').' → '.($to ? $to->format('d M Y') : 'aaj')" :back="route('vendors.show', $vendor)" />

<form method="GET" action="{{ route('reports.vendor', $vendor) }}" class="card-x no-print">
    <div class="row g-2">
        <div class="col-6 field mb-2"><label for="from">Se</label><input type="date" id="from" name="from" class="form-control-x" value="{{ $from?->toDateString() }}"></div>
        <div class="col-6 field mb-2"><label for="to">Tak</label><input type="date" id="to" name="to" class="form-control-x" value="{{ $to?->toDateString() }}" max="{{ now()->toDateString() }}"></div>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-funnel"></i> Filter</button>
        <a href="{{ route('reports.vendor', [$vendor] + $q + ['format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </div>
</form>

<div class="card-x">
    <div class="kv"><span>Opening</span><span>{{ Money::format($opening) }}</span></div>
    <div class="kv"><span>Bills / debit</span><span class="text-owed">+{{ Money::format($total_debit) }}</span></div>
    <div class="kv"><span>Payments / credit</span><span class="text-adv">−{{ Money::format($total_credit) }}</span></div>
    <div class="kv total"><span>Closing balance</span><span>{{ Money::format($closing) }}</span></div>
</div>

@if (! empty($qty_totals))
    <div class="card-x">
        <div class="card-title">Quantity (is muddat)</div>
        @foreach ($qty_totals as $item)
            <div class="kv"><span>{{ $item['name'] }}</span><span class="fw-bold">{{ $item['label'] }}</span></div>
        @endforeach
    </div>
@endif

<div class="tbl-wrap">
    <table class="tbl">
        <thead>
            <tr>
                <th>Date</th>
                <th>Qism</th>
                <th>Qty</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $e)
                @php
                    $qty = ($e->reference_type === 'purchase' && $e->reference_id)
                        ? ($qty_by_purchase[$e->reference_id] ?? null)
                        : null;
                @endphp
                <tr>
                    <td>{{ $e->recorded_at->format('d M, h:i A') }}</td>
                    <td>{{ Labels::ledgerEntry($e->entry_type) }}@if ($e->reference_type === 'purchase' && in_array($e->reference_id, $purchasesWithImage)) <a href="{{ route('invoices.show', $e->reference_id) }}" target="_blank" rel="noopener"><i class="bi bi-image"></i></a>@endif</td>
                    <td>{{ $qty ?: '—' }}</td>
                    <td class="num text-owed">{{ bccomp((string) $e->debit, '0', 2) > 0 ? Money::format($e->debit, false) : '' }}</td>
                    <td class="num text-adv">{{ bccomp((string) $e->credit, '0', 2) > 0 ? Money::format($e->credit, false) : '' }}</td>
                    <td class="num fw-bold">{{ Money::format($e->balance_after, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Is muddat mein koi entry nahi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
