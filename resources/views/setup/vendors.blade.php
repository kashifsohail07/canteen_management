@extends('layouts.app')

@section('title', 'Vendor Setup')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $initial = collect(old('vendors', []))->map(fn ($r) => [
        'name' => $r['name'] ?? '', 'phone' => $r['phone'] ?? '',
        'opening_balance' => $r['opening_balance'] ?? '', 'balance_type' => $r['balance_type'] ?? 'owed',
    ])->values()->all();
@endphp

<x-page-header :title="$isFirstTime ? 'Shuruaat: Vendors' : 'Vendors Add Karein'" sub="Naam aur purana baqaya likhein" :back="$isFirstTime ? null : route('dashboard')" />

@if ($isFirstTime)
    <div class="card-x" style="background: linear-gradient(135deg, #f0f6ff, #eefaf5)">
        <div class="fw-bold mb-1"><i class="bi bi-stars text-primary"></i> Khush aamdeed!</div>
        <div class="small text-muted">Apne vendors ke naam aur unka purana baqaya (jo dena hai ya advance diya hua) likh dein. Baad mein naye vendors kabhi bhi add ho sakte hain.</div>
    </div>
@elseif ($existing->isNotEmpty())
    <div class="card-x">
        <div class="card-title">Maujooda vendors ({{ $existing->count() }})</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($existing as $v)<span class="seg-chip">{{ $v->name }}</span>@endforeach
        </div>
    </div>
@endif

<form method="POST" action="{{ route('setup.vendors.store') }}" x-data="vendorRows(@js($initial))"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    <template x-for="(row, i) in rows" :key="i">
        <div class="setup-row">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge-soft blue" x-text="'Vendor ' + (i + 1)"></span>
                <button type="button" class="btn btn-sm text-danger" x-show="rows.length > 1" @click="remove(i)" aria-label="Hatayein"><i class="bi bi-trash3"></i></button>
            </div>
            <div class="field mb-2">
                <input type="text" class="form-control-x" :name="`vendors[${i}][name]`" x-model="row.name" data-row-name
                       maxlength="120" placeholder="Vendor ka naam" autocapitalize="words" required>
            </div>
            <div class="field mb-2">
                <input type="tel" class="form-control-x" :name="`vendors[${i}][phone]`" x-model="row.phone" maxlength="30" inputmode="tel" placeholder="Phone (optional)">
            </div>
            <div class="pill-group blue mb-2">
                <label class="m-0"><input type="radio" :name="`vendors[${i}][balance_type]`" value="owed" x-model="row.balance_type"><span class="pill" style="min-height:40px">Dena hai</span></label>
                <label class="m-0"><input type="radio" :name="`vendors[${i}][balance_type]`" value="advance" x-model="row.balance_type"><span class="pill" style="min-height:40px">Advance</span></label>
            </div>
            <div class="money-field compact">
                <span class="prefix">Rs.</span>
                <input type="text" class="form-control-x" :name="`vendors[${i}][opening_balance]`" x-model="row.opening_balance"
                       inputmode="decimal" data-money="positive" autocomplete="off" placeholder="Purana baqaya (0)">
            </div>
        </div>
    </template>

    <button type="button" class="btn-xl btn-ghost w-100 mb-3" @click="add()"><i class="bi bi-plus-circle-fill"></i> Aur Vendor Add Karein</button>

    <div class="save-bar">
        <div class="row-btns {{ $isFirstTime ? 'two' : '' }}">
            @if ($isFirstTime)
                <a href="{{ route('dashboard', ['skip_setup' => 1]) }}" class="btn-xl btn-outline-x">Baad Mein</a>
            @endif
            <button type="submit" class="btn-xl btn-save"><i class="bi bi-check2-circle"></i> Vendors Save Karein</button>
        </div>
    </div>
</form>
@endsection
