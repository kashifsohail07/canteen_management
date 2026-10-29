@extends('layouts.app')

@section('title', 'Add Sale')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $locked = $session?->isClosed();
    $selectedRaw = old('sale_segment_id', $selected);
    $selectedId = $selectedRaw !== null && $selectedRaw !== '' ? (int) $selectedRaw : null;
@endphp

<x-page-header title="Add Sale" :sub="$entryDate ? 'Date: '.$date->format('d M Y') : 'Aaj ki bikri likhein'" :back="$back" />

@if ($locked)
    <div class="lock-banner">
        <i class="bi bi-lock-fill"></i>
        <div><strong>Yeh din close hai.</strong><div class="small">Naye entries nahi ho sakte.</div></div>
    </div>
@elseif ($segments->isEmpty())
    <div class="card-x text-center">
        <i class="bi bi-exclamation-circle fs-1 text-warning"></i>
        <div class="fw-bold">Koi sale segment active nahi</div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('segments.index') }}" class="btn-xl btn-sm-x btn-blue mt-2">Segments manage karein</a>
        @else
            <div class="small text-muted">Admin se segment active karwayein.</div>
        @endif
    </div>
@else
<form method="POST" action="{{ route('sales.store') }}"
      x-data="txnForm(@js(['key' => old('idempotency_key'), 'amount' => old('amount', ''), 'segmentId' => $selectedId]))"
      x-init="segmentId = @js($selectedId)"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />

    <div class="field">
        <span class="field-label">Segment</span>
        <div class="pill-group">
            @foreach ($segments as $segment)
                <label class="m-0">
                    <input type="radio" name="sale_segment_id" value="{{ $segment->id }}"
                           x-model.number="segmentId"
                           @change="$nextTick(() => $refs.amountInput?.focus())"
                           required>
                    <span class="pill">
                        {{ $segment->name }}
                        @if ($segment->is_rent_side)
                            <small>side</small>
                        @elseif (isset($totals[$segment->id]) && bccomp($totals[$segment->id]['total'], '0', 2) > 0)
                            <small>{{ Money::format($totals[$segment->id]['total']) }}</small>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <template x-if="segmentId">
        <div>
            <x-money-input name="amount" label="Sale Amount" x-model="amount" x-ref="amountInput" />
        </div>
    </template>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}" placeholder="Koi note…">
    </div>

    @if (($totals ?? collect())->isNotEmpty())
        <div class="card-x">
            <div class="card-title">Aaj ki sale (segment wise)</div>
            @foreach ($totals as $row)
                <div class="kv"><span>{{ $row['name'] }} <small class="text-muted">· {{ $row['count'] }}</small></span><span class="text-in">{{ Money::format($row['total']) }}</span></div>
            @endforeach
        </div>
    @endif

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Save</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Save ho raha hai…</span>
            </button>
        </div>
    </div>
</form>
@endif
@endsection
