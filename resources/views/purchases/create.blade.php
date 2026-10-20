@extends('layouts.app')

@section('title', 'Maal Entry')
@section('main_class', 'has-savebar')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $vendorData = $vendor ? [
        'id' => $vendor->id, 'name' => $vendor->name, 'balance' => \App\Support\Money::of($vendor->current_balance),
        'balance_formatted' => \App\Support\Money::format($vendor->current_balance), 'phone' => $vendor->phone,
        'default_item_name' => $vendor->default_item_name,
        'default_unit' => $vendor->default_unit,
        'default_rate' => $vendor->default_rate !== null ? \App\Support\Money::of($vendor->default_rate) : null,
        'rate_fixed' => $vendor->hasFixedRate(),
    ] : null;
    $oldItems = old('items', []);
    $defaultMode = old('entry_mode', 'itemized');
@endphp

<x-page-header title="Maal Entry" :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Qty likho — total khud aa jayega. Dobara aaye to row add karo.'" :back="$back" />

<form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data" novalidate
      x-data="txnForm(@js([
          'key' => old('idempotency_key'),
          'vendor' => $vendorData,
          'bill' => old('bill_amount', ''),
          'paid' => old('paid_amount', ''),
          'source' => old('payment_source', 'canteen_cash'),
          'entryMode' => $defaultMode,
          'items' => $oldItems,
          'todayUrl' => route('purchases.today-deliveries'),
      ]))"
      @vendor-selected="setVendor($event.detail)"
      @submit="onSubmit($event)">
    <x-form-meta :date="$entryDate" />
    <input type="hidden" name="entry_mode" :value="entryMode">

    <x-vendor-autocomplete :vendor="$vendor" focus="qty_0" />

    <div class="balance-box" x-show="vendor" x-cloak>
        <span>Pehla balance</span>
        <b :class="balanceClass(prev)" x-text="describeBalance(prev)"></b>
    </div>

    {{-- Aaj is vendor se pehle kya aaya --}}
    <div class="today-box" x-show="vendor && todayLines.length" x-cloak>
        <div class="today-head">
            <i class="bi bi-clock-history"></i>
            <span>Aaj pehle aa chuka</span>
            <b x-text="todayQtyTotal + ' pcs · ' + fmt(todayAmountTotal)"></b>
        </div>
        <ul class="today-list">
            <template x-for="(line, i) in todayLines" :key="i">
                <li>
                    <span x-text="line.time"></span>
                    <span x-text="line.item_name"></span>
                    <b x-text="line.quantity + (line.unit ? ' ' + line.unit : '')"></b>
                    <span x-text="fmt(line.line_total)"></span>
                </li>
            </template>
        </ul>
    </div>

    <div class="mode-toggle mb-3" role="tablist" aria-label="Bill type">
        <button type="button" class="mode-btn" :class="entryMode === 'itemized' && 'is-active'" @click="setMode('itemized')">
            <i class="bi bi-plus-slash-minus"></i> Qty se hisab
        </button>
        <button type="button" class="mode-btn" :class="entryMode === 'total' && 'is-active'" @click="setMode('total')">
            <i class="bi bi-lightning-charge-fill"></i> Sirf Total
        </button>
    </div>

    {{-- x-if so hidden required fields are not in the DOM (avoids "not focusable") --}}
    <template x-if="entryMode === 'total'">
        <div>
            <x-money-input name="bill_amount" id="bill_amount" label="Bill Amount" x-model="bill" />
        </div>
    </template>

    <template x-if="entryMode === 'itemized'">
    <div>
        <p class="hint-line mb-2">
            Example: subha 200, phir 50, dopahar 100 — har dafa nayi row.
        </p>

        <template x-for="(row, index) in items" :key="index">
            <div class="item-row card-x mb-2 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="delivery-badge">
                        <i class="bi bi-box-seam"></i>
                        <span x-text="'Delivery ' + (index + 1)"></span>
                    </span>
                    <button type="button" class="btn btn-link text-danger p-0 small" @click="removeItem(index)" aria-label="Row hatayein"
                            x-show="items.length > 1 || row.quantity || row.rate || row.item_name">
                        <i class="bi bi-trash"></i> Hatao
                    </button>
                </div>

                <div class="field mb-2">
                    <label :for="'item_name_'+index">Cheez / Item</label>
                    <input type="text" class="form-control-x" :name="'items['+index+'][item_name]'" :id="'item_name_'+index"
                           x-model="row.item_name" placeholder="Samosay, Roll…" maxlength="120" required>
                </div>

                <div class="qty-grid">
                    <div class="field mb-0">
                        <label :for="'qty_'+index">Kitni Qty?</label>
                        <input type="text" inputmode="numeric" class="form-control-x qty-lg" data-money data-qty-input
                               :name="'items['+index+'][quantity]'" :id="'qty_'+index"
                               x-model="row.quantity" placeholder="200" autocomplete="off">
                    </div>
                    <div class="field mb-0">
                        <label :for="'unit_'+index">Unit</label>
                        <input type="text" class="form-control-x" :name="'items['+index+'][unit]'" :id="'unit_'+index"
                               x-model="row.unit" placeholder="pcs" maxlength="20">
                    </div>
                    <div class="field mb-0">
                        <label :for="'rate_'+index">Rate (1 ka)</label>
                        <input type="text" inputmode="decimal" class="form-control-x" data-money
                               :name="'items['+index+'][rate]'" :id="'rate_'+index"
                               x-model="row.rate" :placeholder="vendor?.default_rate || '20'" autocomplete="off"
                               :readonly="!!vendor?.rate_fixed">
                        <div class="field-hint" x-show="vendor?.rate_fixed" x-cloak>Fix rate — vendor edit se badal sakte hain.</div>
                    </div>
                </div>

                <div class="line-total-bar">
                    <span>Is delivery ka total</span>
                    <b x-text="row.quantity && row.rate ? (row.quantity + ' × ' + row.rate + ' = ' + fmt(lineTotal(row))) : 'Qty × Rate'"></b>
                </div>
            </div>
        </template>

        <button type="button" class="btn-xl btn-add-row w-100 mb-3" @click="addItem()">
            <i class="bi bi-plus-circle-fill"></i> Aur mangwaye — nayi row
        </button>

        <div class="balance-box mb-3 tone-total">
            <div>
                <span>Kul qty</span>
                <div class="small text-muted" x-text="items.length + ' deliveries'"></div>
            </div>
            <div class="text-end">
                <b x-text="itemsQtyTotal ? itemsQtyTotal + ' pcs' : '—'"></b>
                <div class="fw-bold text-primary" x-text="fmt(itemsTotal)"></div>
            </div>
        </div>
    </div>
    </template>

    {{-- Default: udhaar — samosay jaisa vendor shaam ko ek dafa leta hai --}}
    <div class="udhaar-tip card-x mb-3" x-data="{ showPay: {{ old('paid_amount') ? 'true' : 'false' }} }">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-info-circle-fill text-primary mt-1"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">Abhi paisa mat do (default)</div>
                <div class="small text-muted">Samosay / roll wala din bhar maal chhor jata hai. Shaam ko <b>Vendor Ko Diye</b> se jitna cash ho utna de dena.</div>
                <button type="button" class="btn-xl btn-sm-x btn-ghost mt-2" @click="showPay = !showPay"
                        x-text="showPay ? 'Payment chhupao' : 'Abhi hi payment deni hai?'"></button>
            </div>
        </div>

        <template x-if="showPay">
            <div class="mt-3">
                <x-money-input name="paid_amount" id="paid_amount" label="Abhi Diye (Paid)" :required="false" :compact="true" x-model="paid" />
                <div class="d-flex gap-2 mb-2" style="margin-top:-6px">
                    <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="paid = String(Math.round(effectiveBill * 100) / 100)" :disabled="!effectiveBill">Poora diya</button>
                    <button type="button" class="btn-xl btn-sm-x btn-outline-x flex-fill" @click="paid = ''; showPay = false">Udhaar rakho</button>
                </div>
                <div x-show="n(paid) > 0" x-cloak>
                    <x-payment-source />
                </div>
            </div>
        </template>
        <template x-if="!showPay">
            <input type="hidden" name="paid_amount" value="0">
        </template>
    </div>

    <div class="field" x-data="imageCapture()">
        <span class="field-label">Bill Ki Photo (optional)</span>
        <div class="photo-preview" x-show="preview" x-cloak>
            <img :src="preview" alt="Bill preview">
            <button type="button" class="remove" @click="clear()" aria-label="Photo hatayein"><i class="bi bi-x-lg"></i></button>
        </div>
        <div x-show="!preview">
            <label class="photo-drop mb-2" for="bill_image" @click="$refs.file.setAttribute('capture', 'environment')">
                <i class="bi bi-camera-fill"></i><span x-show="!busy">Camera se photo lein</span><span x-show="busy" x-cloak>Compress ho rahi hai…</span>
            </label>
            <button type="button" class="btn-xl btn-sm-x btn-outline-x w-100" @click="$refs.file.removeAttribute('capture'); $refs.file.click()">
                <i class="bi bi-images"></i> Gallery se chunein
            </button>
        </div>
        <input type="file" id="bill_image" name="bill_image" accept="image/*" capture="environment" class="d-none" x-ref="file" @change="pick($event)">
        <div class="field-hint" x-show="info" x-text="info" x-cloak></div>
    </div>

    <div class="field">
        <label for="notes">Note (optional)</label>
        <textarea id="notes" name="notes" class="form-control-x" rows="2" maxlength="500" placeholder="Kya maal aaya…">{{ old('notes') }}</textarea>
    </div>

    <div class="save-bar">
        <div class="summary" x-show="vendor" x-cloak>
            <span>Naya balance</span>
            <b :class="balanceClass(newBalanceBill)" x-text="describeBalance(newBalanceBill)"></b>
        </div>
        <div class="field-error mb-2" x-show="overpaid" x-cloak>Paid zyada hai — advance ban jayega (theek hai).</div>
        <div class="row-btns save-split">
            <button type="submit" name="next" value="0" class="btn-xl btn-save" :class="{ disabled: submitting }">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Save</span>
                <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span>…</span>
            </button>
            <button type="submit" name="next" value="1" class="btn-xl btn-next" :class="{ disabled: submitting }"
                    title="Save karke same vendor par dobara entry">
                <span x-show="!submitting"><i class="bi bi-arrow-repeat"></i> Save & Next</span>
                <span x-show="submitting" x-cloak>…</span>
            </button>
        </div>
    </div>
</form>
@endsection
