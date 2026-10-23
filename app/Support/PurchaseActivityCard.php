<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\PurchaseItem;

/**
 * Display payload for purchase rows in the activity feed.
 * Works for new (meta layout=purchase_v2) and older activities (built from Purchase).
 */
final class PurchaseActivityCard
{
    /**
     * @return array{
     *   qty_label: ?string,
     *   bill: string,
     *   previous: string,
     *   balance: string,
     *   paid: ?string,
     *   on_credit: ?string
     * }|null
     */
    public static function for(ActivityLog $activity, ?Purchase $purchase = null): ?array
    {
        if ($activity->activity_type !== 'purchase') {
            return null;
        }

        $meta = is_array($activity->meta) ? $activity->meta : [];
        if (($meta['layout'] ?? null) === 'purchase_v2' && isset($meta['bill'], $meta['previous'], $meta['balance'])) {
            return [
                'qty_label' => $meta['qty_label'] ?? null,
                'bill' => (string) $meta['bill'],
                'previous' => (string) $meta['previous'],
                'balance' => (string) $meta['balance'],
                'paid' => isset($meta['paid']) ? (string) $meta['paid'] : null,
                'on_credit' => isset($meta['on_credit']) ? (string) $meta['on_credit'] : null,
            ];
        }

        $purchase ??= self::resolvePurchase($activity);
        if (! $purchase) {
            return null;
        }

        $bill = Money::of($purchase->bill_amount);
        $paid = Money::of($purchase->paid_amount);
        $unpaid = Money::sub($bill, $paid);
        $previous = Money::of($purchase->previous_balance);
        $balance = Money::of($purchase->new_balance);

        $card = [
            'qty_label' => self::qtyLabel($purchase),
            'bill' => Money::format($bill),
            'previous' => Money::format($previous),
            'balance' => Money::format($balance),
            'paid' => null,
            'on_credit' => null,
        ];

        if (Money::isPositive($paid)) {
            $pocket = $purchase->payment_source === 'personal_pocket' ? ' (personal pocket)' : '';
            $card['paid'] = Money::format($paid).$pocket;
        }
        if (Money::isPositive($unpaid)) {
            $card['on_credit'] = Money::format($unpaid);
        }

        return $card;
    }

    public static function resolvePurchase(ActivityLog $activity): ?Purchase
    {
        if ($activity->reference_type !== 'purchase' || ! $activity->reference_id) {
            return null;
        }

        if ($activity->relationLoaded('purchaseRef')) {
            /** @var Purchase|null $ref */
            $ref = $activity->getRelation('purchaseRef');

            return $ref;
        }

        return Purchase::with('items')->find($activity->reference_id);
    }

    /** Attach Purchase+items onto a collection of activities (avoids N+1). */
    public static function eagerLoadPurchases($activities): void
    {
        $items = $activities instanceof \Illuminate\Support\Collection
            ? $activities
            : collect($activities);

        $ids = $items
            ->filter(fn ($a) => $a instanceof ActivityLog
                && $a->activity_type === 'purchase'
                && $a->reference_type === 'purchase'
                && $a->reference_id)
            ->pluck('reference_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $purchases = Purchase::with('items')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($items as $activity) {
            if (! $activity instanceof ActivityLog || $activity->activity_type !== 'purchase') {
                continue;
            }
            $activity->setRelation('purchaseRef', $purchases->get($activity->reference_id));
        }
    }

    public static function qtyLabel(Purchase $purchase): ?string
    {
        $items = $purchase->relationLoaded('items') ? $purchase->items : $purchase->items()->get();
        if ($items->isEmpty()) {
            return null;
        }

        $units = $items->pluck('unit')->map(fn ($u) => $u ?: 'pcs')->unique()->values();
        if ($units->count() === 1) {
            $total = '0';
            foreach ($items as $item) {
                $total = bcadd($total, (string) $item->quantity, 3);
            }

            return self::trimQty($total).' '.$units->first();
        }

        return $items
            ->map(fn (PurchaseItem $item) => self::trimQty((string) $item->quantity).' '.($item->unit ?: 'pcs'))
            ->implode(' + ');
    }

    private static function trimQty(string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.') ?: '0';
    }
}
