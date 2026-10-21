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

        $monthStats = $this->monthSalesStats($date->year, $date->month);

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
            'month_sales_total' => $monthStats['total'],
            'month_sales_avg' => $monthStats['average'],
            'month_days_with_sales' => $monthStats['days_with_sales'],
            'month_label' => $date->format('M Y'),
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

    /**
     * Month-to-date sales (excl. Rent Side): total, days with sales, average per selling day.
     *
     * @return array{total: string, average: string, days_with_sales: int}
     */
    public function monthSalesStats(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $sessionIds = DailySession::whereBetween('business_date', [$start, $end])->pluck('id');
        if ($sessionIds->isEmpty()) {
            return [
                'total' => Money::zero(),
                'average' => Money::zero(),
                'days_with_sales' => 0,
            ];
        }

        $perDay = Sale::query()
            ->whereIn('daily_session_id', $sessionIds)
            ->where('is_reversed', false)
            ->where('payment_method', '!=', 'rent_side')
            ->selectRaw('daily_session_id, SUM(amount) as total')
            ->groupBy('daily_session_id')
            ->pluck('total', 'daily_session_id');

        $total = Money::zero();
        $daysWithSales = 0;
        foreach ($perDay as $dayTotal) {
            $amount = Money::of($dayTotal);
            if (Money::isPositive($amount)) {
                $total = Money::add($total, $amount);
                $daysWithSales++;
            }
        }

        $average = $daysWithSales > 0
            ? Money::of(bcdiv($total, (string) $daysWithSales, 8))
            : Money::zero();

        return [
            'total' => $total,
            'average' => $average,
            'days_with_sales' => $daysWithSales,
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
