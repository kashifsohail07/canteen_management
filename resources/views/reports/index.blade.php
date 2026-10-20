@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<x-page-header title="Reports" sub="PDF ya CSV download karein" :back="route('dashboard')" />

{{-- daily --}}
<form method="GET" action="{{ route('reports.daily') }}" class="card-x">
    <div class="card-title"><i class="bi bi-calendar-day"></i> Daily Report</div>
    <div class="field">
        <label for="daily_date">Date</label>
        <input type="date" id="daily_date" name="date" class="form-control-x" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> Dekhein</button>
        <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
        <button type="submit" name="format" value="csv" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-filetype-csv"></i> CSV</button>
    </div>
</form>

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
            <button type="submit" name="format" value="csv" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-filetype-csv"></i> CSV</button>
        </div>
    @endif
</form>

{{-- monthly --}}
<form method="GET" action="{{ route('reports.monthly') }}" class="card-x">
    <div class="card-title"><i class="bi bi-calendar-month"></i> Monthly Report</div>
    <div class="field-hint mb-2">September, October… — mahina choose karein. Report mein sale wale din ka total bhi aata hai.</div>
    <div class="chip-row mb-3">
        @foreach ($recentMonths as $m)
            <a class="chip-link" href="{{ route('reports.monthly', ['month' => $m->format('Y-m')]) }}">{{ $m->format('F Y') }}</a>
        @endforeach
    </div>
    <div class="field">
        <label for="month">Mahina</label>
        <input type="month" id="month" name="month" class="form-control-x" value="{{ $today->format('Y-m') }}" max="{{ $today->format('Y-m') }}" required>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" name="format" value="html" class="btn-xl btn-sm-x btn-blue flex-fill"><i class="bi bi-eye"></i> Dekhein</button>
        <button type="submit" name="format" value="pdf" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
        <button type="submit" name="format" value="csv" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-filetype-csv"></i> CSV</button>
    </div>
</form>
@endsection
