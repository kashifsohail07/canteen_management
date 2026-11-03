@extends('layouts.app')

@section('title', 'Edit Customer')
@section('main_class', 'has-savebar')

@section('content')
<x-page-header title="Edit Customer" :sub="$customer->name" :back="route('customers.show', $customer)" />

<form method="POST" action="{{ route('customers.update', $customer) }}"
      x-data="{ submitting: false }"
      @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
    @csrf
    @method('PUT')

    <div class="field">
        <label for="name">Customer name</label>
        <input type="text" id="name" name="name" class="form-control-x" value="{{ old('name', $customer->name) }}" maxlength="120" required autocapitalize="words">
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" class="form-control-x" value="{{ old('phone', $customer->phone) }}" maxlength="30" inputmode="tel">
    </div>

    <div class="field">
        <label for="notes">Note</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="3" maxlength="500">{{ old('notes', $customer->notes) }}</textarea>
    </div>

    <label class="check-x mb-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active))>
        <span>Active</span>
    </label>

    <div class="card-x">
        <div class="kv"><span>Current balance</span><span>{{ \App\Support\Money::format($customer->current_balance) }}</span></div>
        <div class="kv"><span>Opening balance</span><span>{{ \App\Support\Money::format($customer->opening_balance) }}</span></div>
        <div class="field-hint">Balance badalne ke liye customer page par Opening / Adjust use karo.</div>
    </div>

    <div class="save-bar">
        <div class="row-btns">
            <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }"><i class="bi bi-check2-circle"></i> Update</button>
        </div>
    </div>
</form>

@if ($isAdmin)
    <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-3"
          onsubmit="return confirm('{{ addslashes($customer->name) }} delete karein?\n\nList se hat jayega. Purana ledger safe rahega.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-xl btn-sm-x btn-danger-soft w-100"><i class="bi bi-trash-fill"></i> Delete customer</button>
    </form>
@endif
@endsection
