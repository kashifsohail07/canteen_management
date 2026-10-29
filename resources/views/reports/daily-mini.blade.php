@extends('layouts.app')

@section('title', 'Daily Mini Report')

@use('App\Support\Money')

@section('content')
@php $q = ['date' => $date->toDateString()]; @endphp

<x-page-header title="Daily Mini Report" :sub="$date->format('l, d M Y')" :back="route('reports.index')">
    <span class="badge-soft {{ $session->isClosed() ? 'red' : 'green' }}">{{ $session->isClosed() ? 'Closed' : 'Open' }}</span>
</x-page-header>

<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('reports.daily-mini', $q + ['format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-blue flex-fill">
        <i class="bi bi-file-earmark-pdf"></i> PDF
    </a>
    <a href="{{ route('reports.daily', $q) }}" class="btn-xl btn-sm-x btn-ghost flex-fill">
        <i class="bi bi-list-ul"></i> Full report
    </a>
</div>

@unless ($has_entries)
    <div class="empty">
        <i class="bi bi-inbox"></i>
        <div class="fw-bold text-dark">Is din koi entry nahi</div>
        <div class="small">Sale, mall ya payment ke baad yahan summary aayegi.</div>
    </div>
@endunless

@if (Money::isPositive($sales_total))
    <div class="card-x">
        <div class="card-title">Aaj ki sale</div>
        <div class="kv total"><span>Total sale</span><span class="text-in">{{ Money::format($sales_total) }}</span></div>
        @foreach ($segments as $seg)
            <div class="kv"><span>{{ $seg['name'] }} <small class="text-muted">· {{ $seg['count'] }}</small></span><span>{{ Money::format($seg['total']) }}</span></div>
        @endforeach
    </div>
@endif

@if ($vendor_rows->isNotEmpty())
    <div class="section-title">
        <span>Vendors (aaj ki entries)</span>
        <small>{{ $vendor_rows->count() }}</small>
    </div>

    <div class="card-x p-0 overflow-hidden">
        <div class="tbl-wrap" style="margin:0;border:0;border-radius:0;box-shadow:none">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Qty</th>
                        <th class="num">Previous balance</th>
                        <th class="num">Today order</th>
                        <th class="num">Today payment</th>
                        <th class="num">Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendor_rows as $row)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $row['name'] }}</div>
                                <div class="small text-muted">
                                    @if ($row['purchase_count']){{ $row['purchase_count'] }} mall @endif
                                    @if ($row['purchase_count'] && $row['payment_count'])· @endif
                                    @if ($row['payment_count']){{ $row['payment_count'] }} pay @endif
                                </div>
                            </td>
                            <td>
                                @if ($row['qty_label'])
                                    <div class="fw-semibold" style="white-space:normal;max-width:140px;line-height:1.25">{{ $row['qty_label'] }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="num">{{ Money::format($row['opening']) }}</td>
                            <td class="num text-owed">{{ Money::format($row['mall']) }}</td>
                            <td class="num text-pay">{{ Money::format($row['paid']) }}</td>
                            <td class="num fw-bold">{{ Money::format($row['remaining']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td></td>
                        <td class="num"></td>
                        <td class="num text-owed">{{ Money::format($mall_total) }}</td>
                        <td class="num text-pay">{{ Money::format($paid_total) }}</td>
                        <td class="num"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@if ($expenses->isNotEmpty())
    <div class="card-x">
        <div class="card-title">Kharcha</div>
        @foreach ($expenses as $e)
            <div class="kv">
                <span>{{ $e->category?->name ?? 'Kharcha' }}@if ($e->notes) <small class="text-muted">· {{ $e->notes }}</small>@endif</span>
                <span class="text-pay">{{ Money::format($e->amount) }}</span>
            </div>
        @endforeach
        <div class="kv total"><span>Total kharcha</span><span class="text-pay">{{ Money::format($expense_total) }}</span></div>
    </div>
@endif
@endsection
