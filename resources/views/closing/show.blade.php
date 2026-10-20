@extends('layouts.app')

@section('title', 'Din Close')
@if ($session->isOpen() && $canClose)
    @section('main_class', 'has-savebar')
@endif

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $s = $summary;
    $closed = $session->isClosed();
    $back = route('dashboard', $date->isToday() ? [] : ['date' => $date->toDateString()]);
    $hasMismatchBadge = $closed && $s['cash_difference'] !== null && bccomp((string) $s['cash_difference'], '0', 2) !== 0;
@endphp

<x-page-header title="Din Ka Hisab" :sub="$date->format('l, d M Y')" :back="$back">
    <span class="badge-soft {{ $closed ? 'red' : 'green' }}"><i class="bi {{ $closed ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i> {{ $closed ? 'Closed' : 'Open' }}</span>
</x-page-header>

@if ($closed)
    <div class="lock-banner">
        <i class="bi bi-lock-fill"></i>
        <div>
            <strong>Din close ho chuka hai.</strong>
            <div class="small">
                {{ $session->closed_at?->format('d M, h:i A') }}@if ($session->closer) · {{ $session->closer->name }}@endif
            </div>
        </div>
    </div>
@endif

{{-- expected cash hero --}}
<section class="hero-cash">
    <div class="label"><i class="bi bi-calculator"></i> {{ $closed ? 'Ginti Ka Cash' : 'Expected Closing Cash' }}</div>
    <div class="amount">{{ Money::format($closed ? $s['actual_counted_cash'] : $s['expected_closing_cash']) }}</div>
    <div class="meta">
        <span>Opening {{ Money::format($s['opening_canteen_cash']) }}</span>
        @if ($closed)
            <span>Expected {{ Money::format($session->expected_closing_cash) }}</span>
            <span>Farq {{ Money::format($s['cash_difference']) }}</span>
        @endif
    </div>
</section>

@if ($closed && $hasMismatchBadge)
    <div class="flash flash-error"><i class="bi bi-exclamation-triangle-fill"></i><div><b>Farq ki wajah:</b> {{ $s['difference_reason'] }}</div></div>
@endif

@if ($closed && ! empty($session->close_checklist))
    @php $cl = $session->close_checklist; @endphp
    @if (! empty($cl['segments']) || ! empty($cl['rent']))
        <div class="card-x">
            <div class="card-title">Close checklist (wajah)</div>
            @foreach (($cl['segments'] ?? []) as $segId => $why)
                @php $segName = \App\Models\SaleSegment::find($segId)?->name ?? ('Segment #'.$segId); @endphp
                <div class="kv"><span>{{ $segName }}</span><span class="small">{{ $why }}</span></div>
            @endforeach
            @if (! empty($cl['rent']))
                <div class="kv"><span>Rent Side</span><span class="small">{{ $cl['rent'] }}</span></div>
            @endif
        </div>
    @endif
@endif

{{-- cash movement --}}
<div class="card-x">
    <div class="card-title">Cash Hisab</div>
    <div class="kv"><span>Opening cash</span><span>{{ Money::format($s['opening_canteen_cash']) }}</span></div>
    @forelse ($s['cash_in'] as $type => $amount)
        <div class="kv"><span><i class="bi bi-arrow-down-left text-in"></i> {{ Labels::cashTransaction($type) }}</span><span class="text-in">+{{ Money::format($amount) }}</span></div>
    @empty
        <div class="kv"><span class="text-muted">Koi cash aamad nahi</span><span>—</span></div>
    @endforelse
    @foreach ($s['cash_out'] as $type => $amount)
        <div class="kv"><span><i class="bi bi-arrow-up-right text-pay"></i> {{ Labels::cashTransaction($type) }}</span><span class="text-pay">−{{ Money::format($amount) }}</span></div>
    @endforeach
    <div class="kv total"><span>Expected closing cash</span><span>{{ Money::format($s['expected_closing_cash']) }}</span></div>
</div>

{{-- day summary --}}
<div class="card-x">
    <div class="card-title">Din Ka Khulasa</div>
    <div class="kv"><span>Sale ({{ $s['sales_count'] }})</span><span class="text-in">{{ Money::format($s['sales_total']) }}</span></div>
    @if (bccomp($s['non_cash_sales'], '0', 2) > 0)
        <div class="kv"><span>&nbsp;&nbsp;· Non-cash sale</span><span>{{ Money::format($s['non_cash_sales']) }}</span></div>
    @endif
    <div class="kv"><span>Maal / Bills ({{ $s['purchases_count'] }})</span><span>{{ Money::format($s['purchases_total']) }}</span></div>
    <div class="kv"><span>&nbsp;&nbsp;· Bill par diye</span><span>{{ Money::format($s['purchases_paid']) }}</span></div>
    <div class="kv"><span>&nbsp;&nbsp;· Udhaar</span><span class="text-owed">{{ Money::format($s['purchases_on_credit']) }}</span></div>
    <div class="kv"><span>Vendor payments</span><span>{{ Money::format($s['vendor_payments_total']) }}</span></div>
    <div class="kv"><span>&nbsp;&nbsp;· Canteen cash se</span><span>{{ Money::format($s['vendor_payments_from_cash']) }}</span></div>
    <div class="kv"><span>&nbsp;&nbsp;· Personal pocket se</span><span>{{ Money::format($s['vendor_payments_from_pocket']) }}</span></div>
    <div class="kv"><span>Kharcha</span><span class="text-pay">{{ Money::format($s['expenses_total']) }}</span></div>
    <div class="kv"><span>Rent side rakha</span><span>{{ Money::format($s['rent_reserved']) }}</span></div>
    <div class="kv"><span>Rent diya</span><span>{{ Money::format($s['rent_paid']) }}</span></div>
    <div class="kv"><span>Owner deposit</span><span>{{ Money::format($s['owner_deposits']) }}</span></div>
    <div class="kv"><span>Owner ko wapis</span><span>{{ Money::format($s['owner_repayments']) }}</span></div>
</div>

<div class="card-x">
    <div class="card-title">Balances (Abhi)</div>
    <div class="kv"><span>Vendors ko dena hai</span><span class="text-owed">{{ Money::format($s['vendors_outstanding']) }}</span></div>
    <div class="kv"><span>Rent reserve</span><span>{{ Money::format($s['rent_reserve_balance']) }}</span></div>
    <div class="kv"><span>Owner recoverable</span><span>{{ Money::format($s['owner_recoverable_balance']) }}</span></div>
</div>

<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('reports.daily', ['date' => $date->toDateString()]) }}" class="btn-xl btn-sm-x btn-ghost flex-fill"><i class="bi bi-file-earmark-text"></i> Daily Report</a>
    <a href="{{ route('reports.daily', ['date' => $date->toDateString(), 'format' => 'pdf']) }}" class="btn-xl btn-sm-x btn-outline-x flex-fill"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
</div>

{{-- close day --}}
@if (! $closed)
    @if ($canClose)
        <form method="POST" action="{{ route('closing.close') }}" id="close-form"
              x-data="txnForm(@js(['key' => old('idempotency_key'), 'counted' => old('actual_counted_cash', $s['expected_closing_cash']), 'expected' => $s['expected_closing_cash']]))"
              @submit="onSubmit($event)">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date->toDateString() }}">

            <div class="divider-label">Din Close Karein</div>

            @php
                $gaps = $checklist ?? ['segments' => [], 'rent_missing' => false];
                $hasGaps = ! empty($gaps['segments']) || ! empty($gaps['rent_missing']);
            @endphp

            @if ($hasGaps)
                <div class="card-x mb-3">
                    <div class="card-title"><i class="bi bi-exclamation-circle text-warning"></i> Khali fields — wajah zaroori</div>
                    <div class="field-hint mb-3">Jin segments / Rent Side pe aaj value nahi, unki wajah likhein — tabhi din close hoga.</div>

                    @foreach ($gaps['segments'] as $gap)
                        <div class="field">
                            <label for="seg_reason_{{ $gap['segment_id'] }}">
                                {{ $gap['name'] }} <span class="badge-soft red">Rs. 0</span>
                            </label>
                            <input type="text" id="seg_reason_{{ $gap['segment_id'] }}"
                                   name="segment_reasons[{{ $gap['segment_id'] }}]"
                                   class="form-control-x" maxlength="500" required
                                   value="{{ old('segment_reasons.'.$gap['segment_id']) }}"
                                   placeholder="Kyun entry nahi? jaise: holiday / sale kam">
                            @error('segment_reasons.'.$gap['segment_id'])
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                </div>
            @endif

            <x-money-input name="actual_counted_cash" id="actual_counted_cash" label="Gallay Mein Asal Cash (Ginti)" x-model="counted"
                           hint="Cash gin kar likhein. Expected: {{ Money::format($s['expected_closing_cash']) }}" />

            <div class="balance-box" x-show="Math.abs(difference) >= 0.005" x-cloak>
                <span x-text="difference > 0 ? 'Cash zyada hai' : 'Cash kam hai'"></span>
                <b :class="difference > 0 ? 'text-in' : 'text-pay'" x-text="fmt(difference)"></b>
            </div>

            <div class="field" x-show="Math.abs(difference) >= 0.005" x-cloak>
                <label for="difference_reason">Farq Ki Wajah (zaroori)</label>
                <textarea id="difference_reason" name="difference_reason" class="form-control-x" rows="3" maxlength="500"
                          :required="Math.abs(difference) >= 0.005" placeholder="Cash kam/zyada kyun hai?">{{ old('difference_reason') }}</textarea>
            </div>

            <div class="save-bar">
                <div class="row-btns">
                    <button type="submit" class="btn-xl btn-blue" :class="{ disabled: submitting }">
                        <span x-show="!submitting"><i class="bi bi-lock-fill"></i> Din Close Karein</span>
                        <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Close ho raha hai…</span>
                    </button>
                </div>
            </div>
        </form>
    @else
        <div class="card-x text-center">
            <i class="bi bi-shield-lock fs-2 text-primary"></i>
            <div class="fw-bold mt-1">Sirf admin din close kar sakta hai</div>
            <div class="small text-muted">Admin se closing karwayein.</div>
        </div>
    @endif
@elseif ($canReopen)
    <div class="card-x" x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }">
        <div class="card-title">Admin</div>
        <button type="button" class="btn-xl btn-sm-x btn-danger-soft w-100" x-show="!open" @click="open = true">
            <i class="bi bi-unlock-fill"></i> Din Dobara Kholein
        </button>
        <form method="POST" action="{{ route('closing.reopen') }}" x-show="open" x-cloak>
            @csrf
            <input type="hidden" name="business_date" value="{{ $date->toDateString() }}">
            <div class="field">
                <label for="reason">Dobara kholne ki wajah</label>
                <textarea id="reason" name="reason" class="form-control-x" rows="2" maxlength="500" required>{{ old('reason') }}</textarea>
                <div class="field-hint">Closing adjustment reverse ho jayegi aur din dobara open ho jayega.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn-xl btn-sm-x btn-outline-x flex-fill" @click="open = false">Cancel</button>
                <button type="submit" class="btn-xl btn-sm-x btn-danger-soft flex-fill" onclick="return confirm('Kya aap yeh din dobara kholna chahte hain?')">Reopen</button>
            </div>
        </form>
    </div>
@endif
@endsection
