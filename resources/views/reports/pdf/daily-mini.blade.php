@extends('reports.pdf._layout')

@section('title', 'Daily Mini Report')
@section('subtitle', $date->format('l, d M Y').' · '.ucfirst($session->status->value).' · Today\'s entries only')

@use('App\Support\Money')

@section('body')
@unless ($has_entries)
    <p>No entries recorded for this day.</p>
@endunless

@if (Money::isPositive($sales_total))
    <h2>Today's Sales</h2>
    <table class="kv">
        <tr class="strong"><td>Total sales</td><td class="in">{{ Money::format($sales_total) }}</td></tr>
        @foreach ($segments as $seg)
            <tr><td>&nbsp;&nbsp;· {{ $seg['name'] }} ({{ $seg['count'] }})</td><td>{{ Money::format($seg['total']) }}</td></tr>
        @endforeach
    </table>
@endif

@if ($vendor_rows->isNotEmpty())
    <h2>Vendors — Today Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Qty</th>
                <th class="num">Previous Balance</th>
                <th class="num">Today Order</th>
                <th class="num">Today Payment</th>
                <th class="num">Remaining Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vendor_rows as $row)
                <tr>
                    <td>
                        <strong>{{ $row['name'] }}</strong>
                        @if ($row['purchase_count'] || $row['payment_count'])
                            <div style="font-size:8px;color:#6b7280;margin-top:2px">
                                @if ($row['purchase_count'])
                                    {{ $row['purchase_count'] }} {{ $row['purchase_count'] === 1 ? 'order' : 'orders' }}
                                @endif
                                @if ($row['purchase_count'] && $row['payment_count']) · @endif
                                @if ($row['payment_count'])
                                    {{ $row['payment_count'] }} {{ $row['payment_count'] === 1 ? 'payment' : 'payments' }}
                                @endif
                            </div>
                        @endif
                    </td>
                    <td style="white-space:normal;max-width:120px;line-height:1.3">{{ $row['qty_label'] ?: '—' }}</td>
                    <td class="num">{{ Money::format($row['opening']) }}</td>
                    <td class="num owed">{{ Money::format($row['mall']) }}</td>
                    <td class="num out">{{ Money::format($row['paid']) }}</td>
                    <td class="num"><strong>{{ Money::format($row['remaining']) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td></td>
                <td class="num"></td>
                <td class="num">{{ Money::format($mall_total) }}</td>
                <td class="num">{{ Money::format($paid_total) }}</td>
                <td class="num"></td>
            </tr>
        </tfoot>
    </table>
@endif

@if ($expenses->isNotEmpty())
    <h2>Expenses</h2>
    <table class="kv">
        @foreach ($expenses as $e)
            <tr>
                <td>{{ $e->category?->name ?? 'Expense' }}@if ($e->notes) — {{ $e->notes }}@endif</td>
                <td class="out">{{ Money::format($e->amount) }}</td>
            </tr>
        @endforeach
        <tr class="strong"><td>Total expenses</td><td class="out">{{ Money::format($expense_total) }}</td></tr>
    </table>
@endif
@endsection
