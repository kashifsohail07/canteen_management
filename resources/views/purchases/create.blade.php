@extends('layouts.app')

@section('title', 'Add Mall')
@section('main_class', 'has-savebar')

@use('App\Support\Money')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $vendorData = $vendor ? [
        'id' => $vendor->id,
        'name' => $vendor->name,
        'balance' => Money::of($vendor->current_balance),
        'balance_formatted' => Money::format($vendor->current_balance),
        'phone' => $vendor->phone,
        'default_item_name' => $vendor->default_item_name,
        'default_unit' => $vendor->default_unit,
        'default_rate' => $vendor->default_rate !== null ? Money::of($vendor->default_rate) : null,
        'rate_fixed' => $vendor->hasFixedRate(),
    ] : null;
    $isQtyVendor = (bool) ($vendorData['rate_fixed'] ?? false);
    $defaultMode = old('entry_mode', $isQtyVendor ? 'itemized' : 'total');
    $oldItems = old('items', $isQtyVendor ? [[
        'item_name' => $vendor->default_item_name ?: $vendor->name,
        'quantity' => '',
        'unit' => $vendor->default_unit ?: 'pcs',
        'rate' => Money::of($vendor->default_rate),
    ]] : []);
@endphp

<x-page-header
    title="Add Mall"
    :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Qty vendor: pcs likho · baaki: bill amount'"
    :back="$back"
/>

<form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data" novalidate
      x-data="txnForm(@js([
          'key' => old('idempotency_key'),
          'vendor' => $vendorData,
          'bill' => old('bill_amount', ''),
          'paid' => '0',
          'source' => 'canteen_cash',
          'entryMode' => $defaultMode,
          'items' => $oldItems,
          'todayUrl' => route('purchases.today-deliveries'),
      ]))"
      @vendor-selected="setVendor($event.detail)"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />
    <input type="hidden" name="entry_mode" :value="entryMode">
    <input type="hidden" name="paid_amount" value="0">

    <x-vendor-autocomplete :vendor="$vendor" :focus="$isQtyVendor ? 'qty_0' : 'bill_amount'" label="Vendor" />

    <template x-if="!vendor">
        <p class="hint-line mb-0">Select a vendor first to enter the bill.</p>
    </template>

    <template x-if="vendor">
        <div>
            <div class="balance-box">
                <span>Previous balance</span>
                <b :class="balanceClass(prev)"
                   x-text="Math.abs(prev) < 0.005 ? 'Clear' : (prev > 0 ? fmt(prev) + ' dena hai' : fmt(Math.abs(prev)) + ' advance')"></b>
            </div>

            <div class="today-box" x-show="todayLines.length" x-cloak>
                <div class="today-head">
                    <i class="bi bi-clock-history"></i>
                    <span>Today so far</span>
                    <b x-text="fmt(todayAmountTotal)"></b>
                </div>
                <ul class="today-list">
                    <template x-for="(line, i) in todayLines" :key="i">
                        <li>
                            <span x-text="line.time"></span>
                            <span x-text="line.item_name || 'Mall'"></span>
                            <span x-show="line.quantity && line.quantity !== '1'" x-text="line.quantity + (line.unit ? ' ' + line.unit : '')"></span>
                            <span x-text="fmt(line.line_total)"></span>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Quantity vendor: qty × fixed rate --}}
            <template x-if="vendor?.rate_fixed">
                <div>
                    <div class="balance-box tone-total mb-3">
                        <div>
                            <span>Fix rate</span>
                            <div class="small text-muted" x-text="(vendor.default_item_name || vendor.name) + ' · per pcs'"></div>
                        </div>
                        <b class="text-primary" x-text="fmt(vendor.default_rate)"></b>
                    </div>

                    <template x-for="(row, index) in items" :key="index">
                        <div>
                            <input type="hidden" :name="'items['+index+'][item_name]'" :value="row.item_name">
                            <input type="hidden" :name="'items['+index+'][unit]'" :value="row.unit || 'pcs'">
                            <input type="hidden" :name="'items['+index+'][rate]'" :value="row.rate">

                            <div class="field">
                                <label :for="'qty_'+index">Kitni qty?</label>
                                <input type="text" inputmode="numeric" class="form-control-x qty-lg" data-money data-qty-input
                                       :name="'items['+index+'][quantity]'" :id="'qty_'+index"
                                       x-model="row.quantity" placeholder="200" autocomplete="off" required>
                            </div>

                            <div class="line-total-bar mb-3">
                                <span>Bill</span>
                                <b x-text="row.quantity ? (row.quantity + ' × ' + row.rate + ' = ' + fmt(lineTotal(row))) : 'Qty × Rate'"></b>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Normal vendor: bill amount only --}}
            <template x-if="!vendor?.rate_fixed">
                <div>
                    <x-money-input name="bill_amount" id="bill_amount" label="Bill amount" x-model="bill" />
                </div>
            </template>

            <div class="mall-balance-sheet" x-show="effectiveBill > 0" x-cloak>
                <div class="vbs-row">
                    <span>Previous balance</span>
                    <b :class="balanceClass(prev)" x-text="fmt(prev)"></b>
                </div>
                <div class="vbs-row add">
                    <span>+ This mall</span>
                    <b x-text="fmt(effectiveBill)"></b>
                </div>
                <div class="vbs-row total">
                    <span>New balance</span>
                    <b :class="balanceClass(newBalanceBill)"
                       x-text="Math.abs(newBalanceBill) < 0.005 ? 'Clear' : (newBalanceBill > 0 ? fmt(newBalanceBill) + ' dena hai' : fmt(Math.abs(newBalanceBill)) + ' advance')"></b>
                </div>
            </div>

            <div class="field" x-data="imageCapture()">
                <span class="field-label">Bill photo (optional)</span>
                <div class="photo-preview" x-show="preview" x-cloak>
                    <img :src="preview" alt="Bill preview">
                    <button type="button" class="remove" @click="clear()" aria-label="Remove photo"><i class="bi bi-x-lg"></i></button>
                </div>
                <div x-show="!preview" class="photo-actions">
                    <label class="photo-drop mb-0" for="bill_image" @click="$refs.file.setAttribute('capture', 'environment')">
                        <i class="bi bi-camera-fill"></i>
                        <span x-show="!busy">Take photo</span>
                        <span x-show="busy" x-cloak>Compressing…</span>
                    </label>
                    <button type="button" class="btn-xl btn-sm-x btn-outline-x" @click="$refs.file.removeAttribute('capture'); $refs.file.click()">
                        <i class="bi bi-images"></i> Gallery
                    </button>
                </div>
                <input type="file" id="bill_image" name="bill_image" accept="image/*" capture="environment" class="d-none" x-ref="file" @change="pick($event)">
                <div class="field-hint" x-show="info" x-text="info" x-cloak></div>
            </div>

            <div class="field">
                <label for="notes">Note (optional)</label>
                <input type="text" id="notes" name="notes" class="form-control-x" maxlength="500"
                       value="{{ old('notes') }}" placeholder="What came in…">
            </div>
        </div>
    </template>

    <template x-if="vendor">
        <div class="save-bar">
            <div class="row-btns">
                <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                    <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Save</span>
                    <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Saving…</span>
                </button>
            </div>
        </div>
    </template>
</form>
@endsection
