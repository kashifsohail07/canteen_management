{{-- CSRF + idempotency key (Alpine fills a UUID when missing) + business day for the entry --}}
@props(['date' => null])
@php
    $businessDate = old('business_date', $date);
@endphp
@csrf
<input type="hidden" name="idempotency_key" x-model="key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
@if ($businessDate)
    <input type="hidden" name="business_date" value="{{ $businessDate }}">
@endif
