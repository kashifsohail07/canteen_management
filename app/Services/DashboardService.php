<?php

namespace App\Services;

use App\Enums\CashAccountCode;
use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Models\RentObligation;
use App\Models\Sale;
use App\Models\SaleSegment;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private CashLedgerService $cash,
        private DailyClosingService $closing,
        private RentService $rent,
    ) {}

    /**
     * Headline numbers for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(DailySession $session): array
    {
        $balances = $this->cash->getBalances();
        $day = $this->closing->summarize($session);

        $date = $session->business_date;
        $obligation = RentObligation::where('year', $date->year)->where('month', $date->month)->first()
            ?? $this->rent->ensureObligation($date->year, $date->month);

        $vendorsOwed = Money::zero();
        Vendor::where('is_active', true)->where('current_balance', '>', 0)->get(['current_balance'])
            ->each(function ($v) use (&$vendorsOwed) {
                $vendorsOwed = Money::add($vendorsOwed, $v->current_balance);
            });

        return [
            'business_date' => $date->toDateString(),
            'status' => $session->status->value,
            'day_type' => $session->day_type->value,
            'canteen_cash' => $balances[CashAccountCode::CanteenCash->value],
            'rent_reserve' => $balances[CashAccountCode::RentReserve->value],
            'owner_recoverable' => $balances[CashAccountCode::OwnerRecoverable->value],
            'vendors_owed' => $vendorsOwed,
            'sales_today' => $day['sales_total'],
            'purchases_today' => $day['purchases_total'],
            'expenses_today' => $day['expenses_total'],
            'expected_closing_cash' => $day['expected_closing_cash'],
            'rent' => [
                'year' => $obligation->year,
                'month' => $obligation->month,
                'target' => Money::of($obligation->target_amount),
                'reserved_total' => Money::of($obligation->reserved_total),
                'paid_total' => Money::of($obligation->paid_total),
                'remaining' => $obligation->remainingToPay(),
            ],
        ];
    }

    /** Today's activity feed, newest first. */
    public function activity(DailySession $session, int $perPage = 20): LengthAwarePaginator
    {
        return ActivityLog::with('vendor')
            ->where('daily_session_id', $session->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Sale totals per segment for the session (all active segments, zero included).
     *
     * @return Collection<int, array{segment_id: int, name: string, slug: string, total: string, count: int}>
     */
    public function segmentTotals(DailySession $session): Collection
    {
        $sales = Sale::where('daily_session_id', $session->id)
            ->where('is_reversed', false)
            ->get(['sale_segment_id', 'amount'])
            ->groupBy('sale_segment_id');

        return SaleSegment::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (SaleSegment $segment) use ($sales) {
                $rows = $sales->get($segment->id, collect());
                $total = Money::zero();
                foreach ($rows as $row) {
                    $total = Money::add($total, $row->amount);
                }

                return [
                    'segment_id' => $segment->id,
                    'name' => $segment->name,
                    'slug' => $segment->slug,
                    'total' => $total,
                    'count' => $rows->count(),
                ];
            });
    }
}
