{{-- CSRF + idempotency key (Alpine fills a UUID when missing) + optional back-dated business date --}}
@props(['date' => null])
@csrf
<input type="hidden" name="idempotency_key" x-model="key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
@if ($date || old('business_date'))
    <input type="hidden" name="business_date" value="{{ old('business_date', $date) }}">
@endif
