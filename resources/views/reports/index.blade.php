@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<x-page-header title="Reports" sub="PDF download karein" :back="route('dashboard')" />

{{-- daily mini (compact vendor summary) --}}
<form method="GET" action="{{ route('reports.daily-mini') }}" class="card-x">
    <div class="card-title"><i class="bi bi-file-earmark-text"></i> Daily Mini Report</div>
    <div class="field-hint mb-2">Sirf aaj ki entries — har vendor: pehle balance, mall, payment, baqi. Chhoti PDF summary.</div>
    <div class="field">
        <label for="mini_date">Date</label>
        <input type="date" id="mini_date" name="date" class="form-control-x" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> Dekhein</button>
        <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    </div>
</form>

{{-- daily full --}}
<form method="GET" action="{{ route('reports.daily') }}" class="card-x">
    <div class="card-title"><i class="bi bi-calendar-day"></i> Daily Full Report</div>
    <div class="field-hint mb-2">Poori detail: cash, activity, purchases, payments.</div>
    <div class="field">
        <label for="daily_date">Date</label>
        <input type="date" id="daily_date" name="date" class="form-control-x" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> Dekhein</button>
        <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    </div>
</form>

{{-- all vendors snapshot --}}
<div class="card-x">
    <div class="card-title"><i class="bi bi-people-fill"></i> All Vendors (balances)</div>
    <div class="field-hint mb-2">Har vendor ka current balance + cash in hand — PDF.</div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.vendors', ['format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </div>
</div>

{{-- vendor ledger --}}
<form method="GET" class="card-x" x-data="{ vendor: '{{ $vendors->first()?->id }}' }" :action="'{{ url('reports/vendor') }}/' + vendor">
    <div class="card-title"><i class="bi bi-person-lines-fill"></i> Vendor Ledger</div>
    @if ($vendors->isEmpty())
        <div class="small text-muted">Abhi koi vendor nahi.</div>
    @else
        <div class="field">
            <label for="report_vendor">Vendor</label>
            <select id="report_vendor" class="form-select-x" x-model="vendor">
                @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
            </select>
        </div>
        <div class="row g-2 mb-1">
            <div class="col-6 field mb-2"><label for="vfrom">Se</label><input type="date" id="vfrom" name="from" class="form-control-x"></div>
            <div class="col-6 field mb-2"><label for="vto">Tak</label><input type="date" id="vto" name="to" class="form-control-x" max="{{ $today->toDateString() }}"></div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> Dekhein</button>
            <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
        </div>
    @endif
</form>

{{-- monthly summary --}}
<div class="card-x">
    <div class="card-title"><i class="bi bi-pie-chart-fill"></i> Monthly Summary</div>
    <div class="field-hint mb-2">One screen: item qty, vendors payable, cash, rent saving, top purchaser.</div>
    <div class="chip-row mb-3">
        @foreach ($recentMonths as $m)
            <a class="chip-link" href="{{ route('reports.monthly-summary', ['month' => $m->format('Y-m')]) }}">{{ $m->format('F Y') }}</a>
        @endforeach
    </div>
    <a href="{{ route('reports.monthly-summary', ['month' => $today->format('Y-m')]) }}" class="btn-xl btn-sm-x btn-blue w-100"><i class="bi bi-eye"></i> View</a>
</div>

{{-- monthly day-by-day --}}
<form method="GET" action="{{ route('reports.monthly') }}" class="card-x">
    <div class="card-title"><i class="bi bi-calendar-month"></i> Monthly Day-by-Day</div>
    <div class="field-hint mb-2">Full calendar of sales, mall, payments and rent for the month.</div>
    <div class="chip-row mb-3">
        @foreach ($recentMonths as $m)
            <a class="chip-link" href="{{ route('reports.monthly', ['month' => $m->format('Y-m')]) }}">{{ $m->format('F Y') }}</a>
        @endforeach
    </div>
    <div class="field">
        <label for="month">Month</label>
        <input type="month" id="month" name="month" class="form-control-x" value="{{ $today->format('Y-m') }}" max="{{ $today->format('Y-m') }}" required>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> View</button>
        <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    </div>
</form>

{{-- rent side history --}}
<div class="card-x">
    <div class="card-title"><i class="bi bi-house-lock-fill"></i> Rent Side History</div>
    <div class="field-hint mb-2">Per-month set-aside totals, overall average, open vs closed.</div>
    <a href="{{ route('reports.rent-side') }}" class="btn-xl btn-sm-x btn-blue w-100"><i class="bi bi-eye"></i> View</a>
</div>
@endsection
