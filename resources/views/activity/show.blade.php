@extends('layouts.app')

@section('title', $activity->title)

@use('App\Support\Money')
@use('App\Support\Labels')

@section('content')
@php
    $color = $activity->color instanceof \BackedEnum ? $activity->color->value : (string) $activity->color;
    $sign = Labels::activitySign($activity->activity_type);
    $back = route('dashboard', $activity->dailySession->business_date->isToday() ? [] : ['date' => $activity->dailySession->business_date->toDateString()]);
    $meta = $details['meta'] ?? [];
    $sections = $details['sections'] ?? [];
    $title = $activity->title;
    if ($activity->activity_type === 'purchase' && str_starts_with($title, 'Purchase from ')) {
        $title = substr($title, strlen('Purchase from '));
    }
    if ($activity->activity_type === 'sale' && str_starts_with($title, 'Sale – ')) {
        $title = substr($title, strlen('Sale – '));
    }
    $activityMeta = is_array($activity->meta) ? $activity->meta : [];
    $purchaseLayout = $activity->activity_type === 'purchase' && ($activityMeta['layout'] ?? null) === 'purchase_v2';
    $qtyLabel = ($purchaseLayout && ! empty($activityMeta['qty_label'])) ? $activityMeta['qty_label'] : null;
    $purchaseTotal = $purchaseLayout
        ? ($activityMeta['bill'] ?? Money::format($activity->amount))
        : Money::format($activity->amount);
@endphp

<x-page-header title="Entry Detail" :sub="$activity->recorded_at->format('D, d M Y · h:i A')" :back="$back" />

<div class="act-list mb-3">
    @if ($activity->activity_type === 'purchase')
        <div class="purchase-card">
            <div class="pc-head">
                <span class="pc-icon" style="width:48px;height:48px;font-size:1.25rem"><i class="bi bi-basket2-fill"></i></span>
                <span class="pc-head-body">
                    <span class="pc-name" style="font-size:1.05rem">{{ $title }}</span>
                    <span class="pc-when">{{ $activity->recorded_at->format('h:i A') }} · {{ $activity->recorded_at->format('d M Y') }}</span>
                </span>
                <span class="badge-soft orange pc-pill">Purchase</span>
            </div>
            <div class="pc-stats {{ $qtyLabel ? '' : 'pc-stats-total-only' }}">
                @if ($qtyLabel)
                    <div class="pc-stat">
                        <span class="pc-stat-label">Qty</span>
                        <span class="pc-stat-value">{{ $qtyLabel }}</span>
                    </div>
                    <span class="pc-stats-divider" aria-hidden="true"></span>
                @endif
                <div class="pc-stat">
                    <span class="pc-stat-label">Total</span>
                    <span class="pc-stat-value pc-stat-total">{{ $purchaseTotal }}</span>
                </div>
            </div>
            @if ($purchaseLayout && ! empty($activityMeta['balance']))
                <div class="pc-balance">
                    <span>Total Balance</span>
                    <strong>{{ $activityMeta['balance'] }}</strong>
                </div>
            @endif
        </div>
    @else
        <div class="act act-{{ $color }}" style="padding:18px 16px">
            <span class="act-icon" style="width:52px;height:52px;font-size:1.4rem"><i class="bi {{ Labels::activityIcon($activity->activity_type) }}"></i></span>
            <span class="act-body">
                <span class="act-title d-block fs-5">{{ $title }}</span>
                @if ($activity->summary)
                    <span class="act-sub d-block" style="-webkit-line-clamp:unset">{{ $activity->summary }}</span>
                @endif
            </span>
            <span class="act-aside">
                @if ($activity->activity_type === 'sale')
                    <span class="badge-soft green pc-pill">Sale</span>
                @endif
                @if ($activity->amount !== null)
                    <span class="act-amount fs-5">{{ $sign }}{{ Money::format($activity->amount) }}</span>
                @endif
            </span>
        </div>
    @endif
</div>

@if ($hasImage)
    <div class="card-x">
        <div class="card-title">Bill photo</div>
        <a href="{{ route('invoices.show', $purchase) }}" target="_blank" rel="noopener" class="photo-preview d-block">
            <img src="{{ route('invoices.show', $purchase) }}" alt="Bill photo" loading="lazy">
        </a>
        <div class="field-hint">Tap photo to open full size.</div>
    </div>
@endif

@if ($meta)
    <div class="card-x detail-card">
        <div class="card-title">When & who</div>
        @foreach ($meta as $row)
            <div class="kv"><span>{{ $row['label'] }}</span><span>{{ $row['value'] }}</span></div>
        @endforeach
    </div>
@endif

@foreach ($sections as $section)
    <div class="card-x detail-card">
        <div class="card-title">{{ $section['title'] }}</div>
        @if (! empty($section['hint']))
            <div class="detail-hint">{{ $section['hint'] }}</div>
        @endif

        @if (! empty($section['items']))
            <div class="detail-items">
                @foreach ($section['items'] as $item)
                    <div class="detail-item">
                        <div class="detail-item-name">{{ $item['name'] }}</div>
                        <div class="detail-item-row">
                            <span>{{ $item['calc'] }}</span>
                            <strong>{{ $item['total'] }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @foreach ($section['rows'] ?? [] as $row)
            <div class="kv {{ ! empty($row['emphasis']) ? 'kv-emphasis' : '' }}">
                <span>{{ $row['label'] }}</span>
                <span>{{ $row['value'] }}</span>
            </div>
        @endforeach
    </div>
@endforeach

@if ($canEditSale ?? false)
    @php
        /** @var \App\Models\Sale $record */
        $selectedSegment = (int) old('sale_segment_id', $record->sale_segment_id);
    @endphp
    <div class="card-x">
        <div class="card-title">Sale Edit (Super User)</div>

        <form id="sale-edit-form" method="POST" action="{{ route('sales.update', $record) }}"
              x-data="{ submitting: false }"
              @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
            @csrf
            @method('PUT')

            <div class="field">
                <span class="field-label">Segment</span>
                <div class="pill-group">
                    @foreach ($segments as $segment)
                        <label class="m-0">
                            <input type="radio" name="sale_segment_id" value="{{ $segment->id }}"
                                   @checked($selectedSegment === $segment->id) required>
                            <span class="pill">{{ $segment->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('sale_segment_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <x-money-input name="amount" label="Sale Amount" :value="Money::of($record->amount)"
                           hint="If entered by mistake, set 0 — cash will adjust back." />

            <div class="field">
                <label for="sale_notes">Note (optional)</label>
                <input type="text" id="sale_notes" name="notes" class="form-control-x" maxlength="500"
                       value="{{ old('notes', $record->notes) }}" placeholder="Any note…">
                @error('notes')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label for="sale_reason">Reason (required)</label>
                <input type="text" id="sale_reason" name="reason" class="form-control-x" maxlength="500"
                       required value="{{ old('reason') }}" placeholder="Why are you changing this?">
                @error('reason')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="field-hint mb-2">Cash drawer adjusts by the difference. Reason is saved in audit log.</div>

            <button type="submit" class="btn-xl btn-sm-x btn-save w-100" :class="{ disabled: submitting }">
                <i class="bi bi-check2-circle"></i> Update Sale
            </button>
        </form>
    </div>

    <div class="card-x" x-data="{ open: false, submitting: false }">
        <div class="card-title text-danger">Delete Sale</div>
        <div class="field-hint mb-2">Ghalti se double entry? Delete se cash wapas adjust hoga aur feed se hat jayegi.</div>

        <button type="button" class="btn-xl btn-sm-x btn-danger-soft w-100" x-show="!open" @click="open = true">
            <i class="bi bi-trash-fill"></i> Delete this sale
        </button>

        <form method="POST" action="{{ route('sales.destroy', $record) }}" x-show="open" x-cloak
              @submit="if (submitting) { $event.preventDefault() } else { submitting = true }"
              onsubmit="return confirm('Yeh sale permanently delete karein?\n\nCash reverse ho jayega aur activity se hat jayegi.')">
            @csrf
            @method('DELETE')

            <div class="field">
                <label for="delete_reason">Reason (required)</label>
                <input type="text" id="delete_reason" name="reason" class="form-control-x" maxlength="500"
                       required value="{{ old('reason') }}" placeholder="e.g. Double entry by mistake">
                @error('reason')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="open = false">Cancel</button>
                <button type="submit" class="btn-xl btn-sm-x btn-danger-soft flex-fill" :class="{ disabled: submitting }">
                    <i class="bi bi-trash-fill"></i> Confirm delete
                </button>
            </div>
        </form>
    </div>
@elseif (auth()->user()?->isSuperUser() && ($record instanceof \App\Models\Sale) && $activity->dailySession->isClosed())
    <div class="card-x">
        <div class="field-hint mb-0"><i class="bi bi-lock-fill"></i> Day is closed — reopen first, then sale can be edited / deleted.</div>
    </div>
@endif

@if ($purchase)
    <div class="card-x" x-data="imageCapture()">
        <div class="card-title">{{ $hasImage ? 'Replace photo' : 'Add photo' }}</div>
        <form method="POST" action="{{ route('invoices.replace', $purchase) }}" enctype="multipart/form-data"
              @submit="if (uploading) { $event.preventDefault() } else { uploading = true }">
            @csrf
            <div class="photo-preview mb-2" x-show="preview" x-cloak>
                <img :src="preview" alt="New bill">
                <button type="button" class="remove" @click="clear()" aria-label="Remove"><i class="bi bi-x-lg"></i></button>
            </div>
            <div x-show="!preview" class="mb-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn-xl btn-sm-x btn-ghost flex-fill" @click="$refs.file.setAttribute('capture', 'environment'); $refs.file.click()"><i class="bi bi-camera-fill"></i> Camera</button>
                    <button type="button" class="btn-xl btn-sm-x btn-outline-x flex-fill" @click="$refs.file.removeAttribute('capture'); $refs.file.click()"><i class="bi bi-images"></i> Gallery</button>
                </div>
            </div>
            <input type="file" name="bill_image" accept="image/*" capture="environment" class="d-none" x-ref="file" @change="pick($event)">
            <div class="field-hint mb-2" x-show="info" x-text="info" x-cloak></div>
            <button type="submit" class="btn-xl btn-sm-x btn-save w-100" x-show="preview" x-cloak :class="{ disabled: uploading }"><i class="bi bi-cloud-arrow-up-fill"></i> Save photo</button>
        </form>
    </div>
@endif

@if ($activity->vendor)
    <a href="{{ route('vendors.show', $activity->vendor) }}" class="btn-xl btn-ghost w-100 mb-2"><i class="bi bi-person-lines-fill"></i> {{ $activity->vendor->name }} ledger</a>
@endif
<a href="{{ $back }}" class="btn-xl btn-outline-x w-100"><i class="bi bi-arrow-left"></i> Back</a>
@endsection
