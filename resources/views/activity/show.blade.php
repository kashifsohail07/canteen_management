@extends('layouts.app')

@section('title', $activity->title)

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $color = $activity->color instanceof \BackedEnum ? $activity->color->value : (string) $activity->color;
    $sign = Labels::activitySign($activity->activity_type);
    $back = route('dashboard', $activity->dailySession->business_date->isToday() ? [] : ['date' => $activity->dailySession->business_date->toDateString()]);
@endphp

<x-page-header title="Entry Detail" :sub="$activity->recorded_at->format('D, d M Y · h:i A')" :back="$back" />

<div class="act-list mb-3">
    <div class="act act-{{ $color }}" style="padding:18px 16px">
        <span class="act-icon" style="width:52px;height:52px;font-size:1.4rem"><i class="bi {{ Labels::activityIcon($activity->activity_type) }}"></i></span>
        <span class="act-body">
            <span class="act-title d-block fs-5">{{ $activity->title }}</span>
            @if ($activity->summary)<span class="act-sub d-block" style="-webkit-line-clamp:unset">{{ $activity->summary }}</span>@endif
        </span>
        @if ($activity->amount !== null)
            <span class="act-amount fs-5">{{ $sign }}{{ Money::format($activity->amount) }}</span>
        @endif
    </div>
</div>

@if ($hasImage)
    <div class="card-x">
        <div class="card-title">Bill Ki Photo</div>
        <a href="{{ route('invoices.show', $purchase) }}" target="_blank" rel="noopener" class="photo-preview d-block">
            <img src="{{ route('invoices.show', $purchase) }}" alt="Bill photo" loading="lazy">
        </a>
        <div class="field-hint">Photo par tap karein — poori size mein khulegi.</div>
    </div>
@endif

<div class="card-x">
    <div class="card-title">Tafseel</div>
    @foreach ($details as $row)
        <div class="kv"><span>{{ $row['label'] }}</span><span>{{ $row['value'] }}</span></div>
    @endforeach
</div>

@if ($purchase)
    <div class="card-x" x-data="imageCapture()">
        <div class="card-title">{{ $hasImage ? 'Photo Badlein' : 'Photo Add Karein' }}</div>
        <form method="POST" action="{{ route('invoices.replace', $purchase) }}" enctype="multipart/form-data"
              @submit="if (uploading) { $event.preventDefault() } else { uploading = true }">
            @csrf
            <div class="photo-preview mb-2" x-show="preview" x-cloak>
                <img :src="preview" alt="Naya bill">
                <button type="button" class="remove" @click="clear()" aria-label="Hatayein"><i class="bi bi-x-lg"></i></button>
            </div>
            <div x-show="!preview" class="mb-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="$refs.file.setAttribute('capture', 'environment'); $refs.file.click()"><i class="bi bi-camera-fill"></i> Camera</button>
                    <button type="button" class="btn-xl btn-sm-x btn-outline-x flex-fill" @click="$refs.file.removeAttribute('capture'); $refs.file.click()"><i class="bi bi-images"></i> Gallery</button>
                </div>
            </div>
            <input type="file" name="bill_image" accept="image/*" capture="environment" class="d-none" x-ref="file" @change="pick($event)">
            <div class="field-hint mb-2" x-show="info" x-text="info" x-cloak></div>
            <button type="submit" class="btn-xl btn-sm-x btn-save w-100" x-show="preview" x-cloak :class="{ disabled: uploading }"><i class="bi bi-cloud-arrow-up-fill"></i> Photo Save Karein</button>
        </form>
    </div>
@endif

@if ($activity->vendor)
    <a href="{{ route('vendors.show', $activity->vendor) }}" class="btn-xl btn-ghost w-100 mb-2"><i class="bi bi-person-lines-fill"></i> {{ $activity->vendor->name }} Ka Ledger</a>
@endif
<a href="{{ $back }}" class="btn-xl btn-outline-x w-100"><i class="bi bi-arrow-left"></i> Wapis</a>
@endsection
