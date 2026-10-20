@extends('reports.pdf._layout')

@section('title', 'Monthly Report')
@section('subtitle', $month->format('F Y'))

@use('App\Support\Money')

@section('body')
<h2>Day by day</h2>
<table>
    <thead>
        <tr><th>Date</th><th>Status</th><th class="num">Sales</th><th class="num">Bills</th><th class="num">Paid on bill</th><th class="num">Vendor pay</th><th class="num">Expenses</th><th class="num">Rent set aside</th><th class="num">Rent paid</th><th class="num">Closing cash</th></tr>
    </thead>
    <tbody>
        @forelse ($rows as $r)
            <tr>
                <td>{{ $r['date']->format('d M (D)') }}</td>
                <td>{{ ucfirst($r['status']) }}</td>
                <td class="num">{{ Money::format($r['sales'], false) }}</td>
                <td class="num">{{ Money::format($r['bills'], false) }}</td>
                <td class="num">{{ Money::format($r['paid_on_bill'], false) }}</td>
                <td class="num">{{ Money::format($r['vendor_payments'], false) }}</td>
                <td class="num">{{ Money::format($r['expenses'], false) }}</td>
                <td class="num">{{ Money::format($r['rent_reserved'], false) }}</td>
                <td class="num">{{ Money::format($r['rent_paid'], false) }}</td>
                <td class="num">{{ $r['closing_cash'] !== null ? Money::format($r['closing_cash'], false) : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="10">No records this month.</td></tr>
        @endforelse
    </tbody>
    @if ($rows->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ Money::format($totals['sales'], false) }}</td>
                <td class="num">{{ Money::format($totals['bills'], false) }}</td>
                <td class="num">{{ Money::format($totals['paid_on_bill'], false) }}</td>
                <td class="num">{{ Money::format($totals['vendor_payments'], false) }}</td>
                <td class="num">{{ Money::format($totals['expenses'], false) }}</td>
                <td class="num">{{ Money::format($totals['rent_reserved'], false) }}</td>
                <td class="num">{{ Money::format($totals['rent_paid'], false) }}</td>
                <td></td>
            </tr>
        </tfoot>
    @endif
</table>

@if ($segments->isNotEmpty())
    <h2>Sales by segment</h2>
    <table>
        <thead><tr><th>Segment</th><th class="num">Entries</th><th class="num">Total</th></tr></thead>
        <tbody>
            @foreach ($segments as $seg)
                <tr><td>{{ $seg['name'] }}</td><td class="num">{{ $seg['count'] }}</td><td class="num">{{ Money::format($seg['total']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($vendors->isNotEmpty())
    <h2>Vendors</h2>
    <table>
        <thead><tr><th>Vendor</th><th class="num">Bills</th><th class="num">Paid</th><th class="num">Balance now</th></tr></thead>
        <tbody>
            @foreach ($vendors as $v)
                <tr><td>{{ $v['name'] }}</td><td class="num">{{ Money::format($v['bills'], false) }}</td><td class="num">{{ Money::format($v['paid'], false) }}</td><td class="num">{{ Money::format($v['balance'], false) }}</td></tr>
            @endforeach
        </tbody>
    </table>
@endif
@endsection
