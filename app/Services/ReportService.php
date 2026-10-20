<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Models\Expense;
use App\Models\OwnerTransaction;
use App\Models\Purchase;
use App\Models\RentPayment;
use App\Models\RentReservation;
use App\Models\Sale;
use App\Models\SaleSegment;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Read-only data builders for the daily, vendor-ledger and monthly reports (HTML, PDF and CSV share them). */
class ReportService
{
    public function __construct(
        private DailyClosingService $closing,
        private DashboardService $dashboard,
    ) {}

    /** @return array<string, mixed> */
    public function daily(DailySession $session): array
    {
        $live = fn ($model) => $model::where('daily_session_id', $session->id)->where('is_reversed', false);

        return [
            'session' => $session,
            'date' => $session->business_date,
            'summary' => $this->closing->summarize($session),
            'segments' => $this->dashboard->segmentTotals($session),
            'sales' => $live(Sale::class)->with('segment')->orderBy('recorded_at')->orderBy('id')->get(),
            'purchases' => $live(Purchase::class)->with('vendor')->orderBy('recorded_at')->orderBy('id')->get(),
            'payments' => $live(VendorPayment::class)->with('vendor')->orderBy('recorded_at')->orderBy('id')->get(),
            'expenses' => $live(Expense::class)->with('category')->orderBy('recorded_at')->orderBy('id')->get(),
            'rentReservations' => $live(RentReservation::class)->orderBy('recorded_at')->get(),
            'rentPayments' => $live(RentPayment::class)->orderBy('recorded_at')->get(),
            'ownerTransactions' => $live(OwnerTransaction::class)->orderBy('recorded_at')->orderBy('id')->get(),
            'activity' => ActivityLog::with('vendor')->where('daily_session_id', $session->id)->orderBy('recorded_at')->orderBy('id')->get(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name', 'current_balance']),
        ];
    }

    /** @return array<string, mixed> */
    public function vendorLedger(Vendor $vendor, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $base = VendorLedgerEntry::where('vendor_id', $vendor->id);

        $opening = $from
            ? Money::of((clone $base)->where('recorded_at', '<', $from->copy()->startOfDay())->orderByDesc('recorded_at')->orderByDesc('id')->value('balance_after'))
            : Money::zero();

        $entries = (clone $base)
            ->when($from, fn ($q) => $q->where('recorded_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('recorded_at', '<=', $to->copy()->endOfDay()))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $debit = $credit = Money::zero();
        foreach ($entries as $entry) {
            $debit = Money::add($debit, $entry->debit);
            $credit = Money::add($credit, $entry->credit);
        }

        $closing = $entries->isNotEmpty()
            ? Money::of($entries->last()->balance_after)
            : ($to && $to->lt(now()->startOfDay()) ? $opening : Money::of($vendor->current_balance));

        $purchaseIds = $entries->where('reference_type', 'purchase')->pluck('reference_id');

        return [
            'vendor' => $vendor,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'entries' => $entries,
            'total_debit' => $debit,
            'total_credit' => $credit,
            'closing' => $closing,
            'purchasesWithImage' => Purchase::whereIn('id', $purchaseIds)->whereNotNull('bill_image_path')->pluck('id')->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function monthly(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $sessions = DailySession::whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('business_date')
            ->get();
        $ids = $sessions->pluck('id');

        $sum = fn (string $model, string $column, array $where = []) => $model::whereIn('daily_session_id', $ids)
            ->where('is_reversed', false)
            ->when($where, fn ($q) => $q->where($where))
            ->selectRaw("daily_session_id, SUM($column) as total")
            ->groupBy('daily_session_id')
            ->pluck('total', 'daily_session_id');

        // Bikri only — Rent Side entries are not counted as sales.
        $sales = Sale::whereIn('daily_session_id', $ids)
            ->where('is_reversed', false)
            ->where('payment_method', '!=', 'rent_side')
            ->selectRaw('daily_session_id, SUM(amount) as total')
            ->groupBy('daily_session_id')
            ->pluck('total', 'daily_session_id');
        $bills = $sum(Purchase::class, 'bill_amount');
        $paidOnBill = $sum(Purchase::class, 'paid_amount');
        $payments = $sum(VendorPayment::class, 'amount');
        $expenses = $sum(Expense::class, 'amount');
        $reserved = $sum(RentReservation::class, 'amount');
        $rentPaid = $sum(RentPayment::class, 'amount');
        $deposits = $sum(OwnerTransaction::class, 'amount', ['type' => 'cash_deposit']);

        $rows = $sessions->map(function (DailySession $s) use ($sales, $bills, $paidOnBill, $payments, $expenses, $reserved, $rentPaid, $deposits) {
            $get = fn (Collection $c) => Money::of($c->get($s->id, 0));

            return [
                'date' => $s->business_date,
                'status' => $s->status->value,
                'day_type' => $s->day_type->label(),
                'sales' => $get($sales),
                'bills' => $get($bills),
                'paid_on_bill' => $get($paidOnBill),
                'vendor_payments' => $get($payments),
                'expenses' => $get($expenses),
                'rent_reserved' => $get($reserved),
                'rent_paid' => $get($rentPaid),
                'owner_deposits' => $get($deposits),
                'closing_cash' => $s->actual_counted_cash ?? $s->expected_closing_cash,
                'difference' => $s->cash_difference,
            ];
        });

        $total = fn (string $key) => $rows->reduce(fn ($carry, $row) => Money::add($carry, $row[$key]), Money::zero());

        $daysWithSales = $rows->filter(fn (array $row) => Money::isPositive($row['sales']))->count();
        $daysRecorded = $rows->count();

        // sales per segment (exclude Rent Side — that is not bikri)
        $segmentNames = SaleSegment::pluck('name', 'id');
        $segments = Sale::whereIn('daily_session_id', $ids)->where('is_reversed', false)
            ->where('payment_method', '!=', 'rent_side')
            ->selectRaw('sale_segment_id, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('sale_segment_id')->get()
            ->map(fn ($r) => ['name' => $segmentNames[$r->sale_segment_id] ?? 'Segment', 'total' => Money::of($r->total), 'count' => (int) $r->cnt])
            ->sortByDesc(fn ($r) => (float) $r['total'])->values();

        // per vendor purchases / payments
        $vendorBills = Purchase::whereIn('daily_session_id', $ids)->where('is_reversed', false)
            ->selectRaw('vendor_id, SUM(bill_amount) as bills, SUM(paid_amount) as paid')->groupBy('vendor_id')->get()->keyBy('vendor_id');
        $vendorPays = VendorPayment::whereIn('daily_session_id', $ids)->where('is_reversed', false)
            ->selectRaw('vendor_id, SUM(amount) as paid')->groupBy('vendor_id')->get()->keyBy('vendor_id');
        $vendorIds = $vendorBills->keys()->merge($vendorPays->keys())->unique();
        $vendors = Vendor::withTrashed()->whereIn('id', $vendorIds)->orderBy('name')->get()->map(fn (Vendor $v) => [
            'name' => $v->name,
            'bills' => Money::of($vendorBills[$v->id]->bills ?? 0),
            'paid' => Money::add($vendorBills[$v->id]->paid ?? 0, $vendorPays[$v->id]->paid ?? 0),
            'balance' => Money::of($v->current_balance),
        ]);

        return [
            'month' => $start,
            'month_label' => $start->format('F Y'), // e.g. September 2026
            'rows' => $rows,
            'days_with_sales' => $daysWithSales,
            'days_recorded' => $daysRecorded,
            'totals' => [
                'sales' => $total('sales'),
                'bills' => $total('bills'),
                'paid_on_bill' => $total('paid_on_bill'),
                'vendor_payments' => $total('vendor_payments'),
                'expenses' => $total('expenses'),
                'rent_reserved' => $total('rent_reserved'),
                'rent_paid' => $total('rent_paid'),
                'owner_deposits' => $total('owner_deposits'),
            ],
            'segments' => $segments,
            'vendors' => $vendors,
        ];
    }
}
