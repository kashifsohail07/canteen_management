@extends('layouts.app')

@section('title', 'Add Udhaar')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Add Udhaar" sub="Sale mein nahi — sirf customer hisaab" :back="route('customers.index')" />

<form method="POST" action="{{ route('customers.udhaar.store') }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @if ($entryDate)<input type="hidden" name="business_date" value="{{ $entryDate }}">@endif

    <x-customer-autocomplete :customer="$customer" focus="notes" />

    <div class="field">
        <label for="notes">Kya liya? (note)</label>
        <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500" required
               value="{{ old('notes') }}" placeholder="e.g. 5 rolls / chai + samosa" autofocus>
        @error('notes')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <x-money-input name="amount" label="Total amount" :value="old('amount')" hint="Udhaar amount — daily sale mein nahi jayega." />

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Save Udhaar</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Saving…</span>
            </button>
        </div>
    </div>
</form>
@endsection
