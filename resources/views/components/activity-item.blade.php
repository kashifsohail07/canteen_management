@props(['activity'])
@php
    $a = $activity;
    $color = $a->color instanceof \BackedEnum ? $a->color->value : (string) $a->color;
    $sign = \App\Support\Labels::activitySign($a->activity_type);
    $secondaryLabel = match ($a->activity_type) {
        'purchase' => 'Paid',
        'vendor_payment' => 'Balance',
        'owner_deposit', 'owner_repayment' => 'Owner bal.',
        'rent_reserve' => 'Reserve',
        'rent_payment' => 'Baqi',
        'day_closed' => 'Farq',
        default => null,
    };
@endphp
<a href="{{ route('activity.show', $a) }}" class="act act-{{ $color }}" data-activity-id="{{ $a->id }}">
    <span class="act-icon"><i class="bi {{ \App\Support\Labels::activityIcon($a->activity_type) }}"></i></span>
    <span class="act-body">
        <span class="act-title d-block">{{ $a->title }}@if ($a->has_image) <i class="bi bi-image text-muted ms-1" title="Bill photo"></i>@endif</span>
        @if ($a->summary)<span class="act-sub d-block">{{ $a->summary }}</span>@endif
        <span class="act-time">{{ $a->recorded_at->format('h:i A') }}</span>
    </span>
    @if ($a->amount !== null)
        <span class="act-amount">
            {{ $sign }}{{ \App\Support\Money::format($a->amount) }}
            @if ($a->secondary_amount !== null && $secondaryLabel)
                <small>{{ $secondaryLabel }} {{ \App\Support\Money::format($a->secondary_amount) }}</small>
            @endif
        </span>
    @endif
</a>
