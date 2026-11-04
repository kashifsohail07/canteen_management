@extends('layouts.app')

@section('title', 'Add Required Item')
@section('main_class', 'has-savebar')

@section('content')
@php $classOptions = \App\Models\RequiredItem::CLASS_OPTIONS; @endphp

<x-page-header title="Add Required Item" sub="Write what is needed from market" :back="route('required-items.index')" />

<form method="POST" action="{{ route('required-items.store') }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf

    <div class="field">
        <label for="title">Item name</label>
        <input type="text" id="title" name="title" class="form-control-x" value="{{ old('title') }}"
               maxlength="160" required autofocus placeholder="e.g. Physics Practical Book">
        @error('title')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label for="class_name">Class</label>
        <select id="class_name" name="class_name" class="form-select-x" required>
            <option value="" disabled {{ old('class_name') ? '' : 'selected' }}>Select class</option>
            @foreach ($classOptions as $opt)
                <option value="{{ $opt }}" @selected(old('class_name') === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        @error('class_name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="row g-2">
        <div class="col-7 field">
            <label for="quantity_requested">Qty needed (optional)</label>
            <input type="number" id="quantity_requested" name="quantity_requested" class="form-control-x"
                   value="{{ old('quantity_requested') }}" min="0" step="any" inputmode="decimal" placeholder="e.g. 2">
        </div>
        <div class="col-5 field">
            <label for="unit">Unit</label>
            <input type="text" id="unit" name="unit" class="form-control-x" value="{{ old('unit', 'pcs') }}"
                   maxlength="30" placeholder="pcs">
        </div>
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="2" maxlength="500"
                  placeholder="Student name or any detail">{{ old('notes') }}</textarea>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <i class="bi bi-bag-plus"></i> Save Required Item
            </button>
        </div>
    </div>
</form>
@endsection
