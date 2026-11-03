<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\VendorPayment;
use Illuminate\Support\Collection;

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
     *   paid: string,
     *   on_credit: ?string,
     *   folded_payment_ids: list<int>
     * }|null
     */
    public static function for(ActivityLog $activity, ?Purchase $purchase = null): ?array
    {
        if ($activity->activity_type !== 'purchase') {
            return null;
        }

        $purchase ??= self::resolvePurchase($activity);
        $meta = is_array($activity->meta) ? $activity->meta : [];

        if ($purchase) {
            [$paid, $balance, $foldedIds] = self::foldedPaymentTotals($purchase);

            return [
                'qty_label' => self::qtyLabel($purchase) ?? ($meta['qty_label'] ?? null),
                'bill' => Money::format($purchase->bill_amount),
                'previous' => Money::format($purchase->previous_balance),
                'balance' => Money::format($balance),
                'paid' => Money::format($paid),
                'on_credit' => Money::isPositive(Money::sub($purchase->bill_amount, $paid))
                    ? Money::format(Money::sub($purchase->bill_amount, $paid))
                    : null,
                'folded_payment_ids' => $foldedIds,
            ];
        }

        if (($meta['layout'] ?? null) === 'purchase_v2' && isset($meta['bill'], $meta['previous'], $meta['balance'])) {
            $paidLabel = isset($meta['paid'])
                ? (string) preg_replace('/\s*\(.*\)$/', '', (string) $meta['paid'])
                : Money::format(0);

            return [
                'qty_label' => $meta['qty_label'] ?? null,
                'bill' => (string) $meta['bill'],
                'previous' => (string) $meta['previous'],
                'balance' => (string) $meta['balance'],
                'paid' => $paidLabel !== '' ? $paidLabel : Money::format(0),
                'on_credit' => isset($meta['on_credit']) ? (string) $meta['on_credit'] : null,
                'folded_payment_ids' => [],
            ];
        }

        return null;
    }

    /**
     * Paid on the bill + later same-day standalone payments (until next purchase).
     *
     * @return array{0: string, 1: string, 2: list<int>} [paid_total, balance_after, folded_payment_ids]
     */
    public static function foldedPaymentTotals(Purchase $purchase): array
    {
        $paid = Money::of($purchase->paid_amount);
        $balance = Money::of($purchase->new_balance);
        $foldedIds = [];

        // On-bill payments (already counted in paid_amount) — still hide their feed rows if any.
        $linkedIds = VendorPayment::query()
            ->where('purchase_id', $purchase->id)
            ->where('is_reversed', false)
            ->pluck('id')
            ->all();
        foreach ($linkedIds as $id) {
            $foldedIds[] = (int) $id;
        }
        if ($linkedIds !== []) {
            $lastLinked = VendorPayment::query()
                ->whereIn('id', $linkedIds)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->first();
            if ($lastLinked) {
                $balance = Money::of($lastLinked->new_balance);
            }
        }

        $next = Purchase::query()
            ->where('vendor_id', $purchase->vendor_id)
            ->where('daily_session_id', $purchase->daily_session_id)
            ->where('is_reversed', false)
            ->where(function ($q) use ($purchase) {
                $q->where('recorded_at', '>', $purchase->recorded_at)
                    ->orWhere(function ($q2) use ($purchase) {
                        $q2->where('recorded_at', '=', $purchase->recorded_at)
                            ->where('id', '>', $purchase->id);
                    });
            })
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->first(['id', 'recorded_at']);

        $standalone = VendorPayment::query()
            ->where('vendor_id', $purchase->vendor_id)
            ->where('daily_session_id', $purchase->daily_session_id)
            ->whereNull('purchase_id')
            ->where('is_reversed', false)
            ->where(function ($q) use ($purchase) {
                $q->where('recorded_at', '>', $purchase->recorded_at)
                    ->orWhere(function ($q2) use ($purchase) {
                        $q2->where('recorded_at', '=', $purchase->recorded_at)
                            ->where('id', '>', $purchase->id);
                    });
            });

        if ($next) {
            $standalone->where(function ($q) use ($next) {
                $q->where('recorded_at', '<', $next->recorded_at)
                    ->orWhere(function ($q2) use ($next) {
                        $q2->where('recorded_at', '=', $next->recorded_at)
                            ->where('id', '<', $next->id);
                    });
            });
        }

        foreach ($standalone->orderBy('recorded_at')->orderBy('id')->get() as $payment) {
            $paid = Money::add($paid, $payment->amount);
            $balance = Money::of($payment->new_balance);
            $foldedIds[] = (int) $payment->id;
        }

        return [$paid, $balance, array_values(array_unique($foldedIds))];
    }

    /**
     * Drop vendor_payment feed rows that are already shown on a purchase card.
     *
     * @param  Collection<int, ActivityLog>  $activities
     * @return Collection<int, ActivityLog>
     */
    public static function withoutFoldedPayments(Collection $activities): Collection
    {
        self::eagerLoadPurchases($activities);

        $foldedPaymentIds = [];
        foreach ($activities as $activity) {
            if (! $activity instanceof ActivityLog || $activity->activity_type !== 'purchase') {
                continue;
            }
            $card = self::for($activity);
            if ($card) {
                $activity->setAttribute('purchase_card', $card);
                foreach ($card['folded_payment_ids'] as $id) {
                    $foldedPaymentIds[$id] = true;
                }
            }
        }

        if ($foldedPaymentIds === []) {
            return $activities->values();
        }

        return $activities
            ->reject(fn (ActivityLog $a) => $a->activity_type === 'vendor_payment'
                && $a->reference_type === 'vendor_payment'
                && isset($foldedPaymentIds[(int) $a->reference_id]))
            ->values();
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
                $total = \bcadd($total, (string) $item->quantity, 3);
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
                $bags[$key]['qty'] = \bcadd($bags[$key]['qty'], (string) $item->quantity, 3);
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
                $total = \bcadd($total, (string) $item->quantity, 3);
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
                $rawOverall = \bcadd($rawOverall, $qty, 3);
                if ($day === $todayKey) {
                    $rawToday = \bcadd($rawToday, $qty, 3);
                }
            }
        }

        if (\bccomp($rawOverall, '0', 3) <= 0) {
            return null;
        }

        $days = count($dayKeys);

        return [
            'today' => self::trimQty($rawToday),
            'overall' => self::trimQty($rawOverall),
            'average' => $days > 0 ? self::trimQty(\bcdiv($rawOverall, (string) $days, 3)) : '0',
            'days' => $days,
            'orders' => $purchases->count(),
        ];
    }

    public static function trimQty(string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, '.', ''), '0'), '.') ?: '0';
    }
}
