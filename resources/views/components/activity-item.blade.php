@props([
    'activity',
    'dayOpen' => null,
    'linked' => true,
])
@php
    $a = $activity;
    $color = $a->color instanceof \BackedEnum ? $a->color->value : (string) $a->color;
    $sign = \App\Support\Labels::activitySign($a->activity_type);
    $secondaryLabel = match ($a->activity_type) {
        'purchase' => 'Paid',
        'vendor_payment' => 'Balance',
        'customer_udhaar', 'customer_payment' => 'Balance',
        'vendor_balance_adjust', 'customer_balance_adjust' => 'Ab',
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
    if ($a->activity_type === 'vendor_payment' && str_starts_with($title, 'Paid ')) {
        $title = substr($title, strlen('Paid '));
    }

    $isPurchase = $a->activity_type === 'purchase';
    $purchaseCard = $isPurchase ? \App\Support\PurchaseActivityCard::for($a) : null;
    $qtyLabel = $purchaseCard['qty_label'] ?? null;
    [$pillLabel, $pillColor] = \App\Support\Labels::activityPill($a->activity_type);
    $amountLabel = \App\Support\Labels::activityAmountLabel($a->activity_type);

    $amountText = null;
    if ($purchaseCard) {
        $amountText = $purchaseCard['bill'];
    } elseif ($a->amount !== null) {
        $amountText = $sign.\App\Support\Money::format($a->amount);
    }

    $balanceLabel = null;
    $balanceValue = null;
    if ($purchaseCard && ! empty($purchaseCard['balance'])) {
        $balanceLabel = 'Total Balance';
        $balanceValue = $purchaseCard['balance'];
    } elseif ($a->secondary_amount !== null && $secondaryLabel) {
        $balanceLabel = $secondaryLabel === 'Balance' ? 'Total Balance' : $secondaryLabel;
        $balanceValue = \App\Support\Money::format($a->secondary_amount);
    }

    // Only show free-text summary when there is no structured amount/balance row.
    $summaryHint = (! $amountText && ! $balanceValue && $a->summary)
        ? str_replace("\n", ' · ', $a->summary)
        : null;

    $tone = match ($color) {
        'red' => ['fg' => 'var(--pay-red)', 'bg' => 'var(--pay-red-soft)'],
        'green' => ['fg' => 'var(--brand-green)', 'bg' => 'var(--brand-green-soft)'],
        'orange' => ['fg' => 'var(--balance-orange)', 'bg' => 'var(--balance-orange-soft)'],
        default => ['fg' => 'var(--brand-blue)', 'bg' => 'var(--brand-blue-soft)'],
    };
    // Purchases keep the orange basket look from the mockup.
    if ($isPurchase) {
        $tone = ['fg' => 'var(--balance-orange)', 'bg' => 'var(--balance-orange-soft)'];
    }

    $detailUrl = route('activity.show', $a);
    $tag = $linked ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($linked) href="{{ $detailUrl }}" @endif
    class="purchase-card act-card act-card-{{ $color }}"
    data-activity-id="{{ $a->id }}"
>
    <div class="pc-head">
        <span class="pc-icon" style="color: {{ $tone['fg'] }}; background: {{ $tone['bg'] }}">
            <i class="bi {{ \App\Support\Labels::activityIcon($a->activity_type) }}"></i>
        </span>
        <span class="pc-head-body">
            <span class="pc-name">
                {{ $title }}
                @if ($a->has_image)
                    <i class="bi bi-image text-muted ms-1" title="Bill photo"></i>
                @endif
            </span>
            <span class="pc-when">{{ $a->recorded_at->format('h:i A') }} · {{ $a->recorded_at->format('d M Y') }}</span>
            @if ($summaryHint)
                <span class="pc-when" style="margin-top:2px">{{ $summaryHint }}</span>
            @endif
        </span>
        <span class="badge-soft {{ $pillColor }} pc-pill">{{ $pillLabel }}</span>
    </div>

    @if ($amountText !== null)
        <div class="pc-stats {{ $qtyLabel ? '' : 'pc-stats-total-only' }}">
            @if ($qtyLabel)
                <div class="pc-stat">
                    <span class="pc-stat-label">Qty</span>
                    <span class="pc-stat-value">{{ $qtyLabel }}</span>
                </div>
                <span class="pc-stats-divider" aria-hidden="true"></span>
            @endif
            <div class="pc-stat">
                <span class="pc-stat-label">{{ $amountLabel }}</span>
                <span class="pc-stat-value pc-stat-total" style="color: {{ $tone['fg'] }}">{{ $amountText }}</span>
            </div>
        </div>
    @endif

    @if ($balanceValue)
        <div class="pc-balance">
            <span>{{ $balanceLabel }}</span>
            <strong>{{ $balanceValue }}</strong>
        </div>
    @endif
</{{ $tag }}>
