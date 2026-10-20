@extends('layouts.app')

@section('title', 'Sale Segments')

@section('content')
<x-page-header title="Sale Segments" sub="Break / time slots — apni marzi se add, rename, order" :back="route('dashboard')" />

{{-- Add new --}}
<form method="POST" action="{{ route('segments.store') }}" class="card-x mb-3"
      x-data="{ name: '', submitting: false }"
      @submit="if (submitting || !name.trim()) { $event.preventDefault() } else { submitting = true }">
    @csrf
    <div class="card-title mb-2"><i class="bi bi-plus-circle-fill text-primary"></i> Naya Segment</div>
    <div class="field mb-2">
        <label for="segment_name">Naam</label>
        <input type="text" id="segment_name" name="name" class="form-control-x" maxlength="60"
               x-model="name" value="{{ old('name') }}" placeholder="jaise: Third Break, Staff Tea, Event Sale, Rent Side" required autofocus>
        @error('name') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn-xl btn-save w-100" :class="{ disabled: submitting }">
        <i class="bi bi-check2-circle"></i> Add Karein
    </button>
</form>

<div class="divider-label">Mojooda Segments ({{ $segments->count() }})</div>

@if ($segments->isEmpty())
    <div class="card-x text-center text-muted">
        Abhi koi segment nahi. Upar se pehla add karein.
    </div>
@else
    <div class="list-card segment-manage">
        @foreach ($segments as $i => $segment)
            <div class="segment-card {{ $segment->is_active ? '' : 'is-inactive' }}">
                <form method="POST" action="{{ route('segments.update', $segment) }}" class="segment-edit"
                      x-data="{ submitting: false }"
                      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
                    @csrf
                    @method('PUT')
                    <div class="segment-top">
                        <span class="seg-num">{{ $i + 1 }}</span>
                        <input type="text" name="name" class="form-control-x" maxlength="60"
                               value="{{ old('name_'.$segment->id, $segment->name) }}" required aria-label="Segment name">
                    </div>
                    @if ($segment->is_rent_side)
                        <div class="field-hint mb-1">Rent Side — Add Sale pe pill; amount rent reserve mein jata hai (bikri nahi).</div>
                    @endif
                    <div class="segment-actions">
                        <label class="check-x m-0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active_'.$segment->id, $segment->is_active))>
                            <span class="small">Active</span>
                        </label>
                        <button type="submit" class="btn-xl btn-sm-x btn-ghost">Save</button>
                    </div>
                </form>

                <div class="segment-tools">
                    <form method="POST" action="{{ route('segments.move', $segment) }}">
                        @csrf
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="tool-btn" @disabled($i === 0) title="Upar" aria-label="Upar">
                            <i class="bi bi-chevron-up"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('segments.move', $segment) }}">
                        @csrf
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="tool-btn" @disabled($i === $segments->count() - 1) title="Neeche" aria-label="Neeche">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('segments.destroy', $segment) }}"
                          onsubmit="return confirm('{{ $segment->name }} delete / inactive karein?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="tool-btn danger" title="Delete" aria-label="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<p class="hint-line mt-3">
    Active segments Add Sale screen par pills ki tarah dikhte hain.
    Din close pe agar kisi segment ki value nahi hogi to wajah zaroori hogi.
    Jis segment pe pehle se sales hain, delete pe woh inactive ho jata hai (history safe).
</p>

<div class="mt-3">
    <a href="{{ route('sales.create') }}" class="btn-xl btn-outline-x w-100">
        <i class="bi bi-graph-up-arrow"></i> Add Sale kholein
    </a>
</div>
@endsection
