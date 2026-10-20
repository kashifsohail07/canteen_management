@extends('layouts.app')

@section('title', 'All Vendors')
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

<x-page-header
    title="All Vendors"
    :sub="$entryDate ? 'Date: '.\Illuminate\Support\Carbon::parse($entryDate)->format('d M Y') : 'Mall + payment only — opening balance: Menu → Vendors'"
    :back="$back"
>
    <a href="{{ route('vendors.index') }}" class="btn-xl btn-sm-x btn-ghost"><i class="bi bi-people"></i> Manage</a>
</x-page-header>

<div class="settle-cash-banner">
    <div>
        <div class="small text-muted">Cash in hand</div>
        <div class="settle-cash-amount">{{ Money::format($cashInHand) }}</div>
    </div>
    <i class="bi bi-wallet2"></i>
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
    <div class="vendor-hub" x-data="{
        openId: {{ $initialOpen ?? 'null' }},
        payId: {{ $initialPay ?? 'null' }},
        toggle(id) {
            this.openId = this.openId === id ? null : id;
            if (this.openId !== this.payId) this.payId = null;
        },
        openPay(id) {
            this.openId = id;
            this.payId = id;
            this.$nextTick(() => {
                document.getElementById('amount-' + id)?.focus();
                document.getElementById('pay-panel-' + id)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        }
    }">
        <div class="list-card vendor-hub-list">
            @foreach ($cards as $card)
                @php($owed = bccomp($card['current'], '0', 2))
                <div class="vendor-hub-item" :class="{ open: openId === {{ $card['id'] }} }">
                    <button type="button" class="list-row vendor-hub-toggle"
                            @click="toggle({{ $card['id'] }})"
                            :aria-expanded="(openId === {{ $card['id'] }}).toString()">
                        <span class="vendor-avatar">{{ mb_strtoupper(mb_substr($card['name'], 0, 1)) }}</span>
                        <div class="grow text-start">
                            <div class="name text-truncate">{{ $card['name'] }}</div>
                            <div class="meta">{{ $card['phone'] ?: 'No phone' }}</div>
                        </div>
                        <div class="val {{ $owed > 0 ? 'text-owed' : ($owed < 0 ? 'text-adv' : 'text-muted') }}">
                            {{ Money::format(Money::abs($card['current'])) }}
                            <div class="meta">{{ $owed > 0 ? 'dena hai' : ($owed < 0 ? 'advance' : 'clear') }}</div>
                        </div>
                        <i class="bi bi-chevron-down vendor-hub-chevron"></i>
                    </button>

                    <div class="vendor-hub-panel" x-show="openId === {{ $card['id'] }}" x-cloak>
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

                        {{-- Payment form for this vendor --}}
                        <div id="pay-panel-{{ $card['id'] }}"
                             class="vendor-pay-form"
                             x-show="payId === {{ $card['id'] }}"
                             x-cloak>
                            <form method="POST" action="{{ route('payments.store') }}"
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

                                <div class="settle-actions mb-3">
                                    <button type="button" class="btn-xl btn-settle" @click="fillSettleSuggest()"
                                            :disabled="settleSuggest <= 0">
                                        <i class="bi bi-cash-stack"></i>
                                        <span>
                                            Pay available cash
                                            <small x-show="settleSuggest > 0" x-text="fmt(settleSuggest)"></small>
                                        </span>
                                    </button>
                                    <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="amount = String(prev)"
                                            :disabled="prev <= 0" x-show="prev > 0">
                                        Pay full balance
                                    </button>
                                </div>

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

                                <div class="row-btns mt-2">
                                    <button type="button" class="btn-xl btn-sm-x btn-ghost" @click="payId = null">Cancel</button>
                                    <button type="submit" class="btn-xl btn-save flex-fill" :class="{ disabled: submitting }">
                                        <span x-show="!submitting"><i class="bi bi-send-check-fill"></i> Save Payment</span>
                                        <span x-show="submitting" x-cloak class="busy-inline"><span class="spinner-dot"></span> Saving…</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
