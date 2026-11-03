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
        'rent_reserve' => 'This month',
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
    $purchaseCard = $isPurchase
        ? ($a->getAttribute('purchase_card') ?? \App\Support\PurchaseActivityCard::for($a))
        : null;
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
        $balanceLabel = 'Remaining';
        $balanceValue = $purchaseCard['balance'];
    } elseif ($a->activity_type === 'rent_reserve') {
        // Show this calendar month's set-aside total (not the live multi-month reserve bucket).
        static $monthSaved = [];
        $refDate = $a->recorded_at ?? now();
        $cacheKey = $refDate->format('Y-m');
        if (! array_key_exists($cacheKey, $monthSaved)) {
            $monthSaved[$cacheKey] = \App\Models\RentObligation::query()
                ->where('year', (int) $refDate->format('Y'))
                ->where('month', (int) $refDate->format('n'))
                ->value('reserved_total');
        }
        $balanceLabel = 'This month';
        $balanceValue = \App\Support\Money::format($monthSaved[$cacheKey] ?? 0);
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

    @if ($isPurchase && $purchaseCard)
        <div class="pc-lines">
            @if ($qtyLabel)
                <div class="pc-line">
                    <span>Qty</span>
                    <strong>{{ $qtyLabel }}</strong>
                </div>
            @endif
            <div class="pc-line">
                <span>Order</span>
                <strong class="text-owed">{{ $purchaseCard['bill'] }}</strong>
            </div>
            <div class="pc-line">
                <span>Payment</span>
                <strong class="text-pay">{{ $purchaseCard['paid'] }}</strong>
            </div>
        </div>
        <div class="pc-balance">
            <span>Remaining</span>
            <strong>{{ $purchaseCard['balance'] }}</strong>
        </div>
    @elseif ($amountText !== null)
        <div class="pc-stats pc-stats-total-only">
            <div class="pc-stat">
                <span class="pc-stat-label">{{ $amountLabel }}</span>
                <span class="pc-stat-value pc-stat-total" style="color: {{ $tone['fg'] }}">{{ $amountText }}</span>
            </div>
        </div>
        @if ($balanceValue)
            <div class="pc-balance">
                <span>{{ $balanceLabel }}</span>
                <strong>{{ $balanceValue }}</strong>
            </div>
        @endif
    @endif
</{{ $tag }}>
