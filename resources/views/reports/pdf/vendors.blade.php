@use('App\Support\Money')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vendor Balances Report – Canteen Hisab</title>
    <style>
        @page { margin: 24px 26px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.35;
        }

        .header {
            background: #1a56db;
            color: #fff;
            padding: 16px 18px;
            border-radius: 8px;
            margin-bottom: 14px;
        }
        .header-brand {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .header-sub {
            margin-top: 5px;
            font-size: 9.5px;
            opacity: 0.92;
        }
        .header-meta {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.25);
            font-size: 8.5px;
            opacity: 0.88;
        }

        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px 14px;
        }
        .cards td {
            width: 33.33%;
            vertical-align: top;
            border: 1px solid #e5e9f2;
            border-radius: 8px;
            padding: 10px 11px;
            background: #fff;
        }
        .card-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .card-value {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            white-space: nowrap;
        }
        .card-hint {
            margin-top: 3px;
            font-size: 7.5px;
            color: #9ca3af;
        }
        .card-payable { background: #fff7ed; border-color: #fdba74; }
        .card-payable .card-value { color: #d97706; }
        .card-advance { background: #eff6ff; border-color: #93c5fd; }
        .card-advance .card-value { color: #1a56db; }
        .card-net { background: #f3f6fc; border-color: #c7d9f8; }
        .card-net .card-value { color: #1e429f; }

        h2 {
            margin: 16px 0 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #1a56db;
            border-bottom: 1.5px solid #1a56db;
            padding-bottom: 4px;
        }

        .note {
            margin: 0 0 10px;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px solid #e5e9f2;
            border-radius: 6px;
            font-size: 8.5px;
            color: #4b5563;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.data th {
            background: #1a56db;
            color: #fff;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 7px 6px;
        }
        table.data th.num,
        table.data td.num {
            text-align: right;
            white-space: nowrap;
        }
        table.data td {
            padding: 6px;
            border-bottom: 1px solid #e5e9f2;
            font-size: 9.5px;
        }
        table.data tr:nth-child(even) td {
            background: #f8fafc;
        }
        table.data tfoot td {
            font-weight: bold;
            background: #e8f0fe;
            border-top: 1.5px solid #1a56db;
            border-bottom: 0;
            padding: 8px 6px;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 99px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-payable { background: #ffedd5; color: #c2410c; }
        .badge-advance { background: #dbeafe; color: #1d4ed8; }
        .badge-cleared { background: #e5e7eb; color: #4b5563; }

        .amt-payable { color: #d97706; font-weight: bold; }
        .amt-advance { color: #057a55; font-weight: bold; }
        .amt-cleared { color: #6b7280; }

        .legend {
            margin-top: 10px;
            font-size: 8px;
            color: #6b7280;
        }
        .legend span {
            margin-right: 12px;
        }

        .foot {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e9f2;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-brand">Canteen Hisab</div>
        <h1>Vendor Balances Report</h1>
        <div class="header-sub">{{ $scope_label ?? 'All active vendors' }}</div>
        <div class="header-meta">
            Generated on {{ $generated_at->format('l, d F Y · h:i A') }}
            &nbsp;·&nbsp; {{ $vendor_count }} {{ $vendor_count === 1 ? 'vendor' : 'vendors' }}
        </div>
    </div>

    <table class="cards">
        <tr>
            <td class="card-payable" style="width:33.33%">
                <div class="card-label">Total Payable</div>
                <div class="card-value">{{ Money::format($total_owed) }}</div>
                <div class="card-hint">{{ $payable_count }} {{ $payable_count === 1 ? 'vendor' : 'vendors' }} owed</div>
            </td>
            <td class="card-advance" style="width:33.33%">
                <div class="card-label">Total Advance</div>
                <div class="card-value">{{ Money::format($total_advance) }}</div>
                <div class="card-hint">{{ $advance_count }} {{ $advance_count === 1 ? 'vendor' : 'vendors' }} prepaid</div>
            </td>
            <td class="card-net" style="width:33.33%">
                <div class="card-label">Net Position</div>
                <div class="card-value">{{ Money::format($net_total) }}</div>
                <div class="card-hint">Payable − Advance</div>
            </td>
        </tr>
    </table>

    <h2>Vendor Account Details</h2>
    <div class="note">
        <strong>Payable</strong> = amount we still owe the vendor &nbsp;·&nbsp;
        <strong>Advance</strong> = amount already paid ahead &nbsp;·&nbsp;
        <strong>Cleared</strong> = no outstanding balance
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:28px">#</th>
                <th>Vendor Name</th>
                <th>Phone</th>
                <th class="num">Opening Balance</th>
                <th class="num">Current Balance</th>
                <th style="width:72px">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                @php($key = $r['status_key'])
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><strong>{{ $r['name'] }}</strong></td>
                    <td>{{ $r['phone'] }}</td>
                    <td class="num">{{ Money::format($r['opening']) }}</td>
                    <td class="num amt-{{ $key }}">{{ Money::format($r['balance']) }}</td>
                    <td>
                        <span class="badge badge-{{ $key }}">{{ $r['status'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#6b7280;padding:16px">No active vendors found.</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($rows))
            <tfoot>
                <tr>
                    <td colspan="3">Totals · {{ $vendor_count }} vendors · {{ $cleared_count }} cleared</td>
                    <td class="num">—</td>
                    <td class="num">{{ Money::format($net_total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="legend">
        <span><strong>Payable:</strong> {{ Money::format($total_owed) }}</span>
        <span><strong>Advance:</strong> {{ Money::format($total_advance) }}</span>
        <span><strong>Net:</strong> {{ Money::format($net_total) }}</span>
    </div>

    <div class="foot">
        Canteen Hisab · Vendor Balances Report · Confidential · Page generated automatically
    </div>
</body>
</html>
