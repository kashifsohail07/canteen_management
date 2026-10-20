@extends('reports.pdf._layout')

@section('title', 'Daily Report')
@section('subtitle', $date->format('l, d M Y').' · '.ucfirst($session->status->value))

@use('App\Support\Money')
@use('App\Support\Labels')

@section('body')
@php $s = $summary; @endphp

<h2>Cash</h2>
<table class="kv">
    <tr><td>Opening cash</td><td>{{ Money::format($s['opening_canteen_cash']) }}</td></tr>
    @foreach ($s['cash_in'] as $type => $amount)
        <tr><td>+ {{ Labels::cashTransaction($type) }}</td><td class="in">{{ Money::format($amount) }}</td></tr>
    @endforeach
    @foreach ($s['cash_out'] as $type => $amount)
        <tr><td>− {{ Labels::cashTransaction($type) }}</td><td class="out">{{ Money::format($amount) }}</td></tr>
    @endforeach
    <tr class="strong"><td>Expected closing cash</td><td>{{ Money::format($s['expected_closing_cash']) }}</td></tr>
    @if ($s['actual_counted_cash'] !== null)
        <tr><td>Counted cash</td><td>{{ Money::format($s['actual_counted_cash']) }}</td></tr>
        <tr><td>Difference</td><td>{{ Money::format($s['cash_difference']) }}</td></tr>
        @if ($s['difference_reason'])<tr><td>Reason</td><td>{{ $s['difference_reason'] }}</td></tr>@endif
    @endif
</table>

<h2>Summary</h2>
<table class="kv">
    <tr><td>Sales ({{ $s['sales_count'] }})</td><td class="in">{{ Money::format($s['sales_total']) }}</td></tr>
    @foreach ($segments as $seg)
        <tr><td>&nbsp;&nbsp;· {{ $seg['name'] }}</td><td>{{ Money::format($seg['total']) }}</td></tr>
    @endforeach
    <tr><td>Purchases / bills ({{ $s['purchases_count'] }})</td><td>{{ Money::format($s['purchases_total']) }}</td></tr>
    <tr><td>&nbsp;&nbsp;· paid on bill</td><td>{{ Money::format($s['purchases_paid']) }}</td></tr>
    <tr><td>&nbsp;&nbsp;· on credit</td><td class="owed">{{ Money::format($s['purchases_on_credit']) }}</td></tr>
    <tr><td>Vendor payments</td><td>{{ Money::format($s['vendor_payments_total']) }}</td></tr>
    <tr><td>Expenses</td><td class="out">{{ Money::format($s['expenses_total']) }}</td></tr>
    <tr><td>Rent reserved / paid</td><td>{{ Money::format($s['rent_reserved']) }} / {{ Money::format($s['rent_paid']) }}</td></tr>
    <tr><td>Owner deposits / repayments</td><td>{{ Money::format($s['owner_deposits']) }} / {{ Money::format($s['owner_repayments']) }}</td></tr>
    <tr><td>Vendors outstanding</td><td class="owed">{{ Money::format($s['vendors_outstanding']) }}</td></tr>
    <tr><td>Rent reserve balance</td><td>{{ Money::format($s['rent_reserve_balance']) }}</td></tr>
    <tr><td>Owner recoverable</td><td>{{ Money::format($s['owner_recoverable_balance']) }}</td></tr>
</table>

@if ($purchases->isNotEmpty())
    <h2>Purchases</h2>
    <table>
        <thead><tr><th>Time</th><th>Vendor</th><th class="num">Bill</th><th class="num">Paid</th><th class="num">Vendor balance</th></tr></thead>
        <tbody>
            @foreach ($purchases as $p)
                <tr><td>{{ $p->recorded_at->format('h:i A') }}</td><td>{{ $p->vendor->name }}</td><td class="num">{{ Money::format($p->bill_amount) }}</td><td class="num">{{ Money::format($p->paid_amount) }}</td><td class="num">{{ Money::format($p->new_balance) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($payments->isNotEmpty())
    <h2>Vendor payments</h2>
    <table>
        <thead><tr><th>Time</th><th>Vendor</th><th>Source</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($payments as $p)
                <tr><td>{{ $p->recorded_at->format('h:i A') }}</td><td>{{ $p->vendor->name }}</td><td>{{ Labels::source($p->payment_source) }}</td><td class="num">{{ Money::format($p->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($expenses->isNotEmpty())
    <h2>Expenses</h2>
    <table>
        <thead><tr><th>Time</th><th>Category</th><th>Source</th><th>Notes</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($expenses as $e)
                <tr><td>{{ $e->recorded_at->format('h:i A') }}</td><td>{{ $e->category?->name }}</td><td>{{ Labels::source($e->payment_source) }}</td><td>{{ $e->notes }}</td><td class="num">{{ Money::format($e->amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2>Activity log</h2>
<table>
    <thead><tr><th>Time</th><th>Entry</th><th>Details</th><th class="num">Amount</th></tr></thead>
    <tbody>
        @forelse ($activity as $a)
            <tr>
                <td>{{ $a->recorded_at->format('h:i A') }}</td>
                <td>{{ $a->title }}</td>
                <td>{{ $a->summary }}</td>
                <td class="num">{{ $a->amount !== null ? Labels::activitySign($a->activity_type).Money::format($a->amount) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No entries.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
