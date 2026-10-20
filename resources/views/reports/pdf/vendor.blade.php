@extends('reports.pdf._layout')

@section('title', 'Vendor Ledger')
@section('subtitle', $vendor->name.' · '.($from ? $from->format('d M Y') : 'start').' to '.($to ? $to->format('d M Y') : 'today'))

@use('App\Support\Money')
@use('App\Support\Labels')

@section('body')
<table class="kv">
    <tr><td>Opening balance</td><td>{{ Money::format($opening) }}</td></tr>
    <tr><td>Bills (debit)</td><td class="owed">{{ Money::format($total_debit) }}</td></tr>
    <tr><td>Payments (credit)</td><td class="in">{{ Money::format($total_credit) }}</td></tr>
    <tr class="strong"><td>Closing balance</td><td>{{ Money::format($closing) }}</td></tr>
</table>

<h2>Ledger</h2>
<table>
    <thead><tr><th>Date</th><th>Type</th><th>Notes</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
    <tbody>
        @forelse ($entries as $e)
            <tr>
                <td>{{ $e->recorded_at->format('d M Y h:i A') }}</td>
                <td>{{ Labels::ledgerEntry($e->entry_type) }}</td>
                <td>{{ $e->notes }}</td>
                <td class="num">{{ bccomp((string) $e->debit, '0', 2) > 0 ? Money::format($e->debit, false) : '' }}</td>
                <td class="num">{{ bccomp((string) $e->credit, '0', 2) > 0 ? Money::format($e->credit, false) : '' }}</td>
                <td class="num">{{ Money::format($e->balance_after, false) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No entries in this period.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
