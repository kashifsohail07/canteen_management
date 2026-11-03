@extends('layouts.app')

@section('title', 'Receive Payment')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Receive Payment" sub="Cash drawer mein add — closing pe count" :back="route('customers.index')" />

<form method="POST" action="{{ route('customers.payment.store') }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @if ($entryDate)<input type="hidden" name="business_date" value="{{ $entryDate }}">@endif

    <x-customer-autocomplete :customer="$customer" focus="amount" />

    <x-money-input name="amount" id="amount" label="Amount received" :value="old('amount')" hint="Canteen cash badhega." />

    <div class="field">
        <label for="notes">Note (optional)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" value="{{ old('notes') }}" placeholder="e.g. Full clear">
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Save Payment</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Saving…</span>
            </button>
        </div>
    </div>
</form>
@endsection
