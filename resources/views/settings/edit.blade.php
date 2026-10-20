@extends('layouts.app')

@section('title', 'Settings')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Settings" sub="Sirf admin" :back="route('dashboard')" />

<a href="{{ route('segments.index') }}" class="card-x d-flex align-items-center gap-3 mb-3 text-decoration-none">
    <div class="stat-icon" style="background:#dbeafe;color:var(--brand-blue)"><i class="bi bi-ui-checks-grid"></i></div>
    <div class="flex-grow-1">
        <div class="fw-bold text-dark">Sale Segments</div>
        <div class="small text-muted">First Break, Second Break… add / rename / order</div>
    </div>
    <i class="bi bi-chevron-right text-muted"></i>
</a>

<form method="POST" action="{{ route('settings.update') }}" x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="divider-label">Rent</div>
    <x-money-input name="monthly_rent_target" label="Mahine Ka Rent Target" :value="$monthlyRent" :compact="true" />
    <x-money-input name="daily_rent_suggest" label="Roz Rent Side Rakhne Ki Suggestion" :value="$dailySuggest" :compact="true" />

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Settings Save Karein</button>
        </div>
    </div>
</form>
@endsection
