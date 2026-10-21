@extends('layouts.app')

@section('title', 'All Vendors')
@section('main_class', 'has-savebar')
@use('App\Support\Money')

@section('content')
@php
    $back = route('dashboard', $entryDate ? ['date' => $entryDate] : []);
    $q = $dateQuery;
    $initialOpen = ($showPay && $vendor)
        ? (int) $vendor->id
        : (collect($cards)->firstWhere('open')['id'] ?? null);
    $initialPay = ($showPay && $vendor) ? (int) $vendor->id : null;
@endphp

@php
    $pdfVendors = $cards->map(fn ($c) => [
        'id' => $c['id'],
        'name' => $c['name'],
        'balance' => Money::format(Money::abs($c['current'])),
        'owed' => bccomp($c['current'], '0', 2),
    ])->values();
@endphp

<div x-data="{
    openId: {{ $initialOpen ?? 'null' }},
    payId: {{ $initialPay ?? 'null' }},
    pdfOpen: false,
    pdfVendors: @js($pdfVendors),
    selected: [],
    toggle(id) {
        if (this.payId) return;
        this.openId = this.openId === id ? null : id;
    },
    openPay(id) {
        this.openId = id;
        this.payId = id;
        this.$nextTick(() => document.getElementById('amount-' + id)?.focus());
    },
    closePay() {
        this.payId = null;
    },
    openPdf() {
        this.selected = this.pdfVendors.map(v => v.id);
        this.pdfOpen = true;
    },
    closePdf() {
        this.pdfOpen = false;
    },
    get allSelected() {
        return this.pdfVendors.length > 0 && this.selected.length === this.pdfVendors.length;
    },
    toggleAll() {
        this.selected = this.allSelected ? [] : this.pdfVendors.map(v => v.id);
    },
    toggleOne(id) {
        if (this.selected.includes(id)) {
            this.selected = this.selected.filter(x => x !== id);
        } else {
            this.selected.push(id);
        }
    },
    downloadAll() {
        window.location = @js(route('reports.vendors', ['format' => 'pdf', 'all' => 1]));
        this.closePdf();
    },
    downloadSelected() {
        if (!this.selected.length) return;
        const base = @js(route('reports.vendors'));
        const params = new URLSearchParams({ format: 'pdf', all: '0' });
        this.selected.forEach(id => params.append('vendors[]', id));
        window.location = base + '?' + params.toString();
        this.closePdf();
    }
}" @close-pay="closePay()" @keydown.escape.window="if (pdfOpen) closePdf()" :class="{ 'is-paying': payId }" class="vendor-hub-page">

    <div x-show="!payId">
        <x-page-header
            title="All Vendors"
            :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Mall + payment'"
            :back="$back"
        />

        @php($owedCmp = bccomp($vendorsTotal, '0', 2))
        <div class="settle-cash-banner settle-vendors-total settle-vendors-full {{ $owedCmp > 0 ? 'is-owed' : ($owedCmp < 0 ? 'is-adv' : '') }}">
            <div class="settle-vendors-main">
                <div>
                    <div class="small text-muted">All vendors total</div>
                    <div class="settle-cash-amount">{{ Money::format(Money::abs($vendorsTotal)) }}</div>
                    <div class="settle-stat-sub">
                        {{ $owedCmp > 0 ? 'dena hai' : ($owedCmp < 0 ? 'advance' : 'clear') }}
                        · {{ $cards->count() }} {{ $cards->count() === 1 ? 'vendor' : 'vendors' }}
                    </div>
                </div>
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="settle-vendors-cash">
                <span><i class="bi bi-wallet2"></i> Available cash</span>
                <b>{{ Money::format($cashInHand) }}</b>
            </div>
        </div>

        <div class="settle-toolbar">
            <button type="button" class="settle-tool" @click="openPdf()" @if ($cards->isEmpty()) disabled @endif>
                <i class="bi bi-file-earmark-pdf"></i> PDF Report
            </button>
            <a href="{{ route('vendors.index') }}" class="settle-tool">
                <i class="bi bi-people"></i> Manage
            </a>
        </div>
    </div>

    <div x-show="payId" x-cloak>
        <x-page-header title="Add Payment" sub="Amount likho aur Save dabao" :back="null">
            <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="closePay()">
                <i class="bi bi-x-lg"></i> Back
            </button>
        </x-page-header>
    </div>

@if ($cards->isEmpty())
    <div class="act-list">
        <div class="empty">
            <i class="bi bi-people"></i>
            <div class="fw-bold text-dark">No vendors yet</div>
            <a href="{{ route('vendors.create') }}" class="btn-xl btn-sm-x btn-blue mt-3">Add Vendor</a>
        </div>
    </div>
@else
    <div class="vendor-hub" :class="{ 'is-paying': payId }">
        <div class="list-card vendor-hub-list">
            @foreach ($cards as $card)
                @php($owed = bccomp($card['current'], '0', 2))
                <div class="vendor-hub-item"
                     x-show="!payId || payId === {{ $card['id'] }}"
                     :class="{ open: openId === {{ $card['id'] }}, paying: payId === {{ $card['id'] }} }">
                    <button type="button" class="list-row vendor-hub-toggle"
                            @click="toggle({{ $card['id'] }})"
                            :aria-expanded="(openId === {{ $card['id'] }}).toString()"
                            :disabled="payId === {{ $card['id'] }}">
                        <span class="vendor-avatar">{{ mb_strtoupper(mb_substr($card['name'], 0, 1)) }}</span>
                        <div class="grow text-start">
                            <div class="name text-truncate">{{ $card['name'] }}</div>
                            <div class="meta">{{ $card['phone'] ?: 'No phone' }}</div>
                        </div>
                        <div class="val {{ $owed > 0 ? 'text-owed' : ($owed < 0 ? 'text-adv' : 'text-muted') }}">
                            {{ Money::format(Money::abs($card['current'])) }}
                            <div class="meta">{{ $owed > 0 ? 'dena hai' : ($owed < 0 ? 'advance' : 'clear') }}</div>
                        </div>
                        <i class="bi bi-chevron-down vendor-hub-chevron" x-show="!payId"></i>
                    </button>

                    <div class="vendor-hub-panel" x-show="openId === {{ $card['id'] }}" x-cloak>
                        <div x-show="payId !== {{ $card['id'] }}">
                            <div class="vendor-hub-actions">
                                <a href="{{ route('purchases.create', array_merge($q, ['vendor' => $card['id']])) }}"
                                   class="btn-xl btn-sm-x btn-blue">
                                    <i class="bi bi-basket2-fill"></i> Add Mall
                                </a>
                                <button type="button" class="btn-xl btn-sm-x btn-settle"
                                        @click="openPay({{ $card['id'] }})">
                                    <i class="bi bi-send-fill"></i> Add Payment
                                </button>
                            </div>

                            <div class="vendor-balance-sheet">
                                <div class="vbs-row">
                                    <span>Previous balance</span>
                                    <b class="{{ bccomp($card['previous'], '0', 2) > 0 ? 'text-owed' : (bccomp($card['previous'], '0', 2) < 0 ? 'text-adv' : '') }}">
                                        {{ Money::format($card['previous']) }}
                                    </b>
                                </div>
                                @if (bccomp($card['today_mall'], '0', 2) !== 0)
                                    <div class="vbs-row add">
                                        <span>+ Today’s mall</span>
                                        <b>{{ Money::format($card['today_mall']) }}</b>
                                    </div>
                                @endif
                                @if (bccomp($card['today_paid'], '0', 2) !== 0)
                                    <div class="vbs-row pay">
                                        <span>− Payment</span>
                                        <b>{{ Money::format($card['today_paid']) }}</b>
                                    </div>
                                @endif
                                <div class="vbs-row total">
                                    <span>Remaining balance</span>
                                    <b class="{{ $owed > 0 ? 'text-owed' : ($owed < 0 ? 'text-adv' : '') }}">
                                        {{ Money::format($card['remaining']) }}
                                    </b>
                                </div>
                            </div>

                            @if ($card['entries']->isNotEmpty())
                                <div class="vendor-recent">
                                    <div class="vendor-recent-head">Today’s entries</div>
                                    <ul class="vendor-recent-list">
                                        @foreach ($card['entries'] as $entry)
                                            <li class="{{ $entry['kind'] }}">
                                                <div>
                                                    <div class="name">{{ $entry['label'] }}</div>
                                                    <div class="meta">
                                                        {{ $entry['at']->format('h:i A') }}
                                                        @if ($entry['detail']) · {{ $entry['detail'] }}@endif
                                                    </div>
                                                </div>
                                                <b>{{ $entry['signed'] }}{{ Money::format($entry['amount']) }}</b>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <p class="vendor-hub-empty-hint mb-0">No mall or payment recorded today.</p>
                            @endif
                        </div>

                        {{-- Focused payment form --}}
                        <div id="pay-panel-{{ $card['id'] }}"
                             class="vendor-pay-form"
                             x-show="payId === {{ $card['id'] }}"
                             x-cloak>
                            <form method="POST" action="{{ route('payments.store') }}"
                                  class="vendor-pay-form-inner"
                                  x-data="txnForm(@js([
                                      'key' => old('idempotency_key'),
                                      'vendor' => [
                                          'id' => $card['id'],
                                          'name' => $card['name'],
                                          'balance' => $card['current'],
                                          'balance_formatted' => Money::format($card['current']),
                                          'phone' => $card['phone'],
                                      ],
                                      'amount' => (old('vendor_id') == $card['id'] ? old('amount', '') : ''),
                                      'source' => old('payment_source', 'canteen_cash'),
                                      'cashAvailable' => $cashInHand,
                                  ]))"
                                  @submit="onSubmit($event)">
                                <x-form-meta :date="$entryDate" />
                                <input type="hidden" name="vendor_id" value="{{ $card['id'] }}">

                                <div class="vendor-pay-title">
                                    <i class="bi bi-send-fill"></i>
                                    Pay {{ $card['name'] }}
                                </div>

                                <x-money-input name="amount" id="amount-{{ $card['id'] }}" label="Amount to pay" x-model="amount" />

                                <x-payment-source
                                    label="Pay from"
                                    :options="[
                                        ['canteen_cash', 'Canteen Cash', 'From till', 'bi-cash-stack'],
                                        ['personal_pocket', 'Personal Pocket', 'From own pocket', 'bi-wallet2'],
                                    ]"
                                />

                                <div class="field">
                                    <label for="notes-{{ $card['id'] }}">Note (optional)</label>
                                    <input type="text" id="notes-{{ $card['id'] }}" name="notes" class="form-control-x" maxlength="500"
                                           value="{{ old('vendor_id') == $card['id'] ? old('notes') : '' }}"
                                           placeholder="Evening settlement…">
                                </div>

                                <div class="balance-box" x-show="n(amount) > 0" x-cloak>
                                    <span>Balance after payment</span>
                                    <b :class="balanceClass(newBalancePay)"
                                       x-text="Math.abs(newBalancePay) < 0.005 ? 'Clear' : (newBalancePay > 0 ? fmt(newBalancePay) + ' dena hai' : fmt(Math.abs(newBalancePay)) + ' advance')"></b>
                                </div>

                                <div class="save-bar vendor-pay-save">
                                    <div class="row-btns two">
                                        <button type="button" class="btn-xl btn-ghost" @click="$dispatch('close-pay')">Cancel</button>
                                        <button type="submit" class="btn-xl btn-save" :class="{ disabled: submitting }">
                                            <span x-show="!submitting"><i class="bi bi-send-check-fill"></i> Save Payment</span>
                                            <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Saving…</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

    {{-- PDF report picker modal --}}
    <div x-cloak x-show="pdfOpen" class="sheet-backdrop" @click="closePdf()"
         x-transition:enter="fade-enter" x-transition:enter-start="fade-start" x-transition:enter-end="fade-end"
         x-transition:leave="fade-enter" x-transition:leave-start="fade-end" x-transition:leave-end="fade-start"></div>
    <div x-cloak x-show="pdfOpen" class="pdf-modal" role="dialog" aria-label="PDF Report"
         @click.stop
         x-transition:enter="sheet-enter" x-transition:enter-start="sheet-enter-start" x-transition:enter-end="sheet-enter-end"
         x-transition:leave="sheet-enter" x-transition:leave-start="sheet-enter-end" x-transition:leave-end="sheet-enter-start">
        <div class="pdf-modal-head">
            <div>
                <div class="pdf-modal-title"><i class="bi bi-file-earmark-pdf"></i> PDF Report</div>
                <div class="pdf-modal-sub">All vendors ya selected vendors</div>
            </div>
            <button type="button" class="back-btn" @click="closePdf()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>

        <button type="button" class="pdf-all-btn" @click="downloadAll()">
            <i class="bi bi-people-fill"></i>
            <span>
                <strong>All vendors report</strong>
                <small>Har vendor ka balance PDF mein</small>
            </span>
            <i class="bi bi-download"></i>
        </button>

        <div class="pdf-modal-divider">ya select karo</div>

        <div class="pdf-select-bar">
            <label class="check-x mb-0">
                <input type="checkbox" :checked="allSelected" @change="toggleAll()">
                <span>Select all</span>
            </label>
            <span class="meta" x-text="selected.length + ' selected'"></span>
        </div>

        <div class="pdf-vendor-list">
            <template x-for="v in pdfVendors" :key="v.id">
                <label class="pdf-vendor-row">
                    <input type="checkbox" :checked="selected.includes(v.id)" @change="toggleOne(v.id)">
                    <span class="grow">
                        <span class="name" x-text="v.name"></span>
                        <span class="meta" x-text="v.balance + (v.owed > 0 ? ' · dena hai' : (v.owed < 0 ? ' · advance' : ' · clear'))"></span>
                    </span>
                </label>
            </template>
        </div>

        <div class="pdf-modal-actions">
            <button type="button" class="btn-xl btn-ghost" @click="closePdf()">Cancel</button>
            <button type="button" class="btn-xl btn-save flex-fill"
                    :disabled="!selected.length"
                    :class="{ disabled: !selected.length }"
                    @click="downloadSelected()">
                <i class="bi bi-file-earmark-pdf"></i> Download selected
            </button>
        </div>
    </div>
</div>
@endsection
