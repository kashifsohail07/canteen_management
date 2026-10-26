@props([
    'activity',
    'dayOpen' => null,
])
@php
    $a = $activity;
    $color = $a->color instanceof \BackedEnum ? $a->color->value : (string) $a->color;
    $sign = \App\Support\Labels::activitySign($a->activity_type);
    $secondaryLabel = match ($a->activity_type) {
        'purchase' => 'Paid',
        'vendor_payment' => 'Balance',
        'customer_udhaar', 'customer_payment' => 'Balance',
        'owner_deposit', 'owner_repayment' => 'Owner bal.',
        'rent_reserve' => 'Reserve',
        'rent_payment' => 'Baqi',
        'day_closed' => 'Farq',
        default => null,
    };
    $title = $a->title;
    if ($a->activity_type === 'purchase' && str_starts_with($title, 'Purchase from ')) {
        $title = substr($title, strlen('Purchase from '));
    }
    if ($a->activity_type === 'sale' && str_starts_with($title, 'Sale – ')) {
        $title = substr($title, strlen('Sale – '));
    }
    $meta = is_array($a->meta) ? $a->meta : [];
    $isPurchase = $a->activity_type === 'purchase';
    $isSale = $a->activity_type === 'sale';
    $purchaseV2 = $isPurchase && ($meta['layout'] ?? null) === 'purchase_v2';
    $qtyLabel = $purchaseV2 && ! empty($meta['qty_label']) ? $meta['qty_label'] : null;
    $detailUrl = route('activity.show', $a);
    $canSwipeSale = (bool) auth()->user()?->isSuperUser()
        && $isSale
        && $a->reference_id
        && ($dayOpen !== false);
@endphp

@if ($canSwipeSale)
    <div class="swipe-row" x-data="swipeActions()" data-activity-id="{{ $a->id }}">
        <div class="swipe-actions">
            <a href="{{ $detailUrl }}" class="swipe-action swipe-edit" @click.stop>
                <i class="bi bi-pencil-fill"></i>
                <small>Edit</small>
            </a>
            <form method="POST" action="{{ route('sales.destroy', $a->reference_id) }}" class="m-0"
                  @click.stop
                  onsubmit="return confirm('Yeh sale delete karein?\n\nCash reverse ho jayega aur list se hat jayegi.')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="reason" value="Deleted from activity swipe (duplicate / mistake)">
                <button type="submit" class="swipe-action swipe-delete" @click.stop>
                    <i class="bi bi-trash-fill"></i>
                    <small>Delete</small>
                </button>
            </form>
        </div>
        <div class="swipe-front act act-{{ $color }}"
             role="link"
             tabindex="0"
             :class="{ 'is-open': open, 'is-dragging': dragging }"
             :style="'transform: translateX(' + dx + 'px)'"
             @touchstart.passive="onStart($event)"
             @touchend.passive="onEnd()"
             @touchcancel.passive="onEnd()"
             @click.prevent="tap('{{ $detailUrl }}')"
             @keydown.enter="window.location = '{{ $detailUrl }}'">
            <span class="act-icon"><i class="bi {{ \App\Support\Labels::activityIcon($a->activity_type) }}"></i></span>
            <span class="act-body">
                <span class="act-title d-block">{{ $title }}@if ($a->has_image) <i class="bi bi-image text-muted ms-1" title="Bill photo"></i>@endif</span>
                @if ($a->summary)<span class="act-sub d-block">{{ $a->summary }}</span>@endif
                <span class="act-time">{{ $a->recorded_at->format('h:i A') }}</span>
            </span>
            <span class="act-aside">
                <span class="badge-soft green pc-pill">Sale</span>
                @if ($a->amount !== null)
                    <span class="act-amount">
                        {{ $sign }}{{ \App\Support\Money::format($a->amount) }}
                    </span>
                @endif
            </span>
        </div>
    </div>
@elseif ($isPurchase)
    <a href="{{ $detailUrl }}" class="purchase-card" data-activity-id="{{ $a->id }}">
        <div class="pc-head">
            <span class="pc-icon"><i class="bi bi-basket2-fill"></i></span>
            <span class="pc-head-body">
                <span class="pc-name">{{ $title }}@if ($a->has_image) <i class="bi bi-image text-muted ms-1" title="Bill photo"></i>@endif</span>
                <span class="pc-when">{{ $a->recorded_at->format('h:i A') }} · {{ $a->recorded_at->format('d M Y') }}</span>
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
                <span class="pc-stat-value pc-stat-total">{{ $purchaseV2 ? ($meta['bill'] ?? \App\Support\Money::format($a->amount)) : \App\Support\Money::format($a->amount) }}</span>
            </div>
        </div>

        @if ($purchaseV2 && ! empty($meta['balance']))
            <div class="pc-balance">
                <span>Total Balance</span>
                <strong>{{ $meta['balance'] }}</strong>
            </div>
        @elseif ($a->summary)
            <div class="pc-balance pc-balance-muted">
                <span>{{ str_replace("\n", ' · ', $a->summary) }}</span>
            </div>
        @endif
    </a>
@else
    <a href="{{ $detailUrl }}" class="act act-{{ $color }}" data-activity-id="{{ $a->id }}">
        <span class="act-icon"><i class="bi {{ \App\Support\Labels::activityIcon($a->activity_type) }}"></i></span>
        <span class="act-body">
            <span class="act-title d-block">{{ $title }}@if ($a->has_image) <i class="bi bi-image text-muted ms-1" title="Bill photo"></i>@endif</span>
            @if ($a->summary)<span class="act-sub d-block">{{ $a->summary }}</span>@endif
            <span class="act-time">{{ $a->recorded_at->format('h:i A') }}</span>
        </span>
            <span class="act-aside">
            @if ($isSale)
                <span class="badge-soft green pc-pill">Sale</span>
            @elseif ($a->activity_type === 'customer_udhaar')
                <span class="badge-soft orange pc-pill">Udhaar</span>
            @elseif ($a->activity_type === 'customer_payment')
                <span class="badge-soft green pc-pill">Pay</span>
            @endif
            @if ($a->amount !== null)
                <span class="act-amount">
                    {{ $sign }}{{ \App\Support\Money::format($a->amount) }}
                    @if ($a->secondary_amount !== null && $secondaryLabel)
                        <small>{{ $secondaryLabel }} {{ \App\Support\Money::format($a->secondary_amount) }}</small>
                    @endif
                </span>
            @endif
        </span>
    </a>
@endif
