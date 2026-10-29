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

    /**
     * Totals by item name + unit across purchases (for daily / monthly qty checks).
     *
     * @param  iterable<int, Purchase>  $purchases
     * @return list<array{name: string, unit: string, qty: string, label: string, display: string}>
     */
    public static function itemQtyTotals(iterable $purchases): array
    {
        $bags = [];

        foreach ($purchases as $purchase) {
            if (! $purchase instanceof Purchase) {
                continue;
            }
            $items = $purchase->relationLoaded('items') ? $purchase->items : $purchase->items()->get();
            foreach ($items as $item) {
                $name = trim((string) $item->item_name) ?: 'Item';
                $unit = $item->unit ?: 'pcs';
                $key = mb_strtolower($name).'|'.mb_strtolower($unit);
                if (! isset($bags[$key])) {
                    $bags[$key] = ['name' => $name, 'unit' => $unit, 'qty' => '0'];
                }
                $bags[$key]['qty'] = bcadd($bags[$key]['qty'], (string) $item->quantity, 3);
            }
        }

        return collect($bags)
            ->map(function (array $row) {
                $qty = self::trimQty($row['qty']);

                return [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'qty' => $qty,
                    'label' => $qty.' '.$row['unit'],
                    'display' => $row['name'].' '.$qty.' '.$row['unit'],
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** Compact label e.g. "Samosa 200 pcs · Roll 50 pcs". */
    public static function qtySummaryLabel(iterable $purchases): ?string
    {
        $totals = self::itemQtyTotals($purchases);

        return $totals === [] ? null : collect($totals)->pluck('display')->implode(' · ');
    }

    /** Sum of all line quantities as a plain number (e.g. "200"). */
    public static function totalQtyNumber(iterable $purchases): ?string
    {
        $total = '0';
        $hasItems = false;

        foreach ($purchases as $purchase) {
            if (! $purchase instanceof Purchase) {
                continue;
            }
            $items = $purchase->relationLoaded('items') ? $purchase->items : $purchase->items()->get();
            foreach ($items as $item) {
                $hasItems = true;
                $total = bcadd($total, (string) $item->quantity, 3);
            }
        }

        return $hasItems ? self::trimQty($total) : null;
    }

    /**
     * Vendor qty card: today / overall / daily average (numbers only).
     *
     * @return array{today: string, overall: string, average: string, days: int, orders: int}|null
     */
    public static function vendorQtyStats(int $vendorId, ?\Illuminate\Support\Carbon $onDate = null): ?array
    {
        $onDate = $onDate?->copy()->startOfDay() ?? now()->startOfDay();
        $todayKey = $onDate->toDateString();

        $purchases = Purchase::query()
            ->with('items')
            ->where('vendor_id', $vendorId)
            ->where('is_reversed', false)
            ->whereHas('items')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        if ($purchases->isEmpty()) {
            return null;
        }

        $rawOverall = '0';
        $rawToday = '0';
        $dayKeys = [];

        foreach ($purchases as $purchase) {
            $day = $purchase->recorded_at?->toDateString();
            if ($day) {
                $dayKeys[$day] = true;
            }
            foreach ($purchase->items as $item) {
                $qty = (string) $item->quantity;
                $rawOverall = bcadd($rawOverall, $qty, 3);
                if ($day === $todayKey) {
                    $rawToday = bcadd($rawToday, $qty, 3);
                }
            }
        }

        if (bccomp($rawOverall, '0', 3) <= 0) {
            return null;
        }

        $days = count($dayKeys);

        return [
            'today' => self::trimQty($rawToday),
            'overall' => self::trimQty($rawOverall),
            'average' => $days > 0 ? self::trimQty(bcdiv($rawOverall, (string) $days, 3)) : '0',
            'days' => $days,
            'orders' => $purchases->count(),
        ];
    }

    public static function trimQty(string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.') ?: '0';
    }
}
