<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Enums\DailySessionStatus;
use App\Models\ActivityLog;
use App\Models\CashTransaction;
use App\Models\DailySession;
use App\Models\Expense;
use App\Models\OwnerTransaction;
use App\Models\Purchase;
use App\Models\RentPayment;
use App\Models\RentReservation;
use App\Models\Sale;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyClosingService
{
    private const ADJUSTMENT_REF = 'closing_adjustment';

    public function __construct(
        private CashLedgerService $cash,
        private ActivityLogService $activity,
        private AuditService $audit,
    ) {}

    /**
     * End-of-day numbers for a session. Expected closing cash is derived from the day's canteen cash
     * movements (closing adjustments excluded), starting from the opening cash.
     *
     * @return array<string, mixed>
     */
    public function summarize(DailySession $session): array
    {
        $canteen = $this->cash->getAccount(CashAccountCode::CanteenCash, false);

        $cashIn = [];
        $cashOut = [];
        $totalIn = Money::zero();
        $totalOut = Money::zero();

        CashTransaction::query()
            ->where('cash_account_id', $canteen->id)
            ->where('daily_session_id', $session->id)
            ->where('is_reversed', false)
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', '!=', self::ADJUSTMENT_REF))
            ->get(['direction', 'transaction_type', 'amount'])
            ->each(function ($tx) use (&$cashIn, &$cashOut, &$totalIn, &$totalOut) {
                if ($tx->direction === 'in') {
                    $cashIn[$tx->transaction_type] = Money::add($cashIn[$tx->transaction_type] ?? 0, $tx->amount);
                    $totalIn = Money::add($totalIn, $tx->amount);
                } else {
                    $cashOut[$tx->transaction_type] = Money::add($cashOut[$tx->transaction_type] ?? 0, $tx->amount);
                    $totalOut = Money::add($totalOut, $tx->amount);
                }
            });

        $opening = Money::of($session->opening_canteen_cash);
        $expected = Money::sub(Money::add($opening, $totalIn), $totalOut);

        $sales = Sale::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['amount', 'payment_method']);
        $salesTotal = $this->sum($sales, 'amount');
        $cashSales = $this->sum($sales->where('payment_method', 'cash'), 'amount');

        $purchases = Purchase::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['bill_amount', 'paid_amount']);
        $purchasesTotal = $this->sum($purchases, 'bill_amount');
        $purchasesPaid = $this->sum($purchases, 'paid_amount');

        $payments = VendorPayment::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['amount', 'payment_source']);

        $accounts = $this->cash->getBalances();

        return [
            'business_date' => $session->business_date->toDateString(),
            'status' => $session->status->value,
            'opening_canteen_cash' => $opening,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'total_cash_in' => $totalIn,
            'total_cash_out' => $totalOut,
            'expected_closing_cash' => $expected,
            'sales_total' => $salesTotal,
            'cash_sales' => $cashSales,
            'non_cash_sales' => Money::sub($salesTotal, $cashSales),
            'sales_count' => $sales->count(),
            'purchases_total' => $purchasesTotal,
            'purchases_paid' => $purchasesPaid,
            'purchases_on_credit' => Money::sub($purchasesTotal, $purchasesPaid),
            'purchases_count' => $purchases->count(),
            'vendor_payments_total' => $this->sum($payments, 'amount'),
            'vendor_payments_from_cash' => $this->sum($payments->where('payment_source', 'canteen_cash'), 'amount'),
            'vendor_payments_from_pocket' => $this->sum($payments->where('payment_source', 'personal_pocket'), 'amount'),
            'expenses_total' => $this->sum(Expense::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['amount']), 'amount'),
            'rent_reserved' => $this->sum(RentReservation::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['amount']), 'amount'),
            'rent_paid' => $this->sum(RentPayment::where('daily_session_id', $session->id)->where('is_reversed', false)->get(['amount']), 'amount'),
            'owner_deposits' => $this->sum(OwnerTransaction::where('daily_session_id', $session->id)->where('type', 'cash_deposit')->where('is_reversed', false)->get(['amount']), 'amount'),
            'owner_repayments' => $this->sum(OwnerTransaction::where('daily_session_id', $session->id)->where('type', 'repayment')->where('is_reversed', false)->get(['amount']), 'amount'),
            'rent_reserve_balance' => $accounts[CashAccountCode::RentReserve->value],
            'owner_recoverable_balance' => $accounts[CashAccountCode::OwnerRecoverable->value],
            'vendors_outstanding' => $this->sum(Vendor::get(['current_balance']), 'current_balance'),
            'actual_counted_cash' => $session->actual_counted_cash,
            'cash_difference' => $session->cash_difference,
            'difference_reason' => $session->difference_reason,
        ];
    }

    /**
     * Close the day: record counted cash, require a reason when it doesn't match, post an adjustment so the
     * canteen cash account equals the counted cash, and lock the session.
     */
    public function close(DailySession $session, string|int|float $actualCash, ?string $reason, User $user): DailySession
    {
        $actual = Money::of($actualCash);
        if (Money::isNegative($actual)) {
            throw ValidationException::withMessages(['actual_counted_cash' => 'Counted cash cannot be negative.']);
        }
        $reason = $reason !== null ? trim($reason) : null;

        return DB::transaction(function () use ($session, $actual, $reason, $user) {
            $locked = DailySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DailySessionStatus::Open) {
                throw ValidationException::withMessages(['business_date' => 'This day is already closed.']);
            }

            $account = $this->cash->getAccount(CashAccountCode::CanteenCash); // lock
            $summary = $this->summarize($locked);
            $expected = $summary['expected_closing_cash'];
            $difference = Money::sub($actual, $expected);

            if (! Money::isZero($difference) && ($reason === null || $reason === '')) {
                throw ValidationException::withMessages([
                    'difference_reason' => 'Counted cash differs from expected ('.Money::format($expected).') by '
                        .Money::format($difference).'. Please give a reason.',
                ]);
            }

            // bring the ledger in line with the physical cash
            $adjustment = Money::sub($actual, $account->balance);
            if (! Money::isZero($adjustment)) {
                $ctx = [
                    'session' => $locked,
                    'reference_type' => self::ADJUSTMENT_REF,
                    'reference_id' => $locked->id,
                    'notes' => $reason ?: 'Closing adjustment',
                    'user' => $user,
                    'allow_negative' => true,
                ];
                Money::isPositive($adjustment)
                    ? $this->cash->credit(CashAccountCode::CanteenCash, $adjustment, 'closing_adjustment', $ctx)
                    : $this->cash->debit(CashAccountCode::CanteenCash, Money::abs($adjustment), 'closing_adjustment', $ctx);
            }

            $balances = $this->cash->getBalances();
            $locked->update([
                'status' => DailySessionStatus::Closed,
                'expected_closing_cash' => $expected,
                'actual_counted_cash' => $actual,
                'cash_difference' => $difference,
                'difference_reason' => Money::isZero($difference) ? null : $reason,
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            $this->activity->log(
                $locked, 'day_closed', 'Day closed',
                Money::isZero($difference)
                    ? 'Cash matched: '.Money::format($actual)
                    : 'Counted '.Money::format($actual).' vs expected '.Money::format($expected).' ('.Money::format($difference).') – '.$reason,
                $actual, $difference,
                Money::isZero($difference) ? ActivityColor::Green : ActivityColor::Orange,
                null, $locked, false,
                ['rent_reserve' => $balances[CashAccountCode::RentReserve->value], 'owner_recoverable' => $balances[CashAccountCode::OwnerRecoverable->value]],
                $user,
            );

            $this->audit->log('daily_session.closed', $locked, ['status' => 'open'], [
                'status' => 'closed',
                'expected_closing_cash' => $expected,
                'actual_counted_cash' => $actual,
                'cash_difference' => $difference,
            ], $reason, $user);

            return $locked->refresh();
        });
    }

    /** Admin-only: reopen a closed day (reverses the closing adjustment). */
    public function reopen(DailySession $session, User $admin, string $reason): DailySession
    {
        if (! $admin->isAdmin()) {
            throw new AuthorizationException('Only an admin can reopen a day.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reopen a day.']);
        }

        return DB::transaction(function () use ($session, $admin, $reason) {
            $locked = DailySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DailySessionStatus::Closed) {
                throw ValidationException::withMessages(['business_date' => 'This day is not closed.']);
            }

            $laterWithActivity = ActivityLog::whereIn(
                'daily_session_id',
                DailySession::whereDate('business_date', '>', $locked->business_date->toDateString())->select('id')
            )->exists();
            if ($laterWithActivity) {
                throw ValidationException::withMessages([
                    'business_date' => 'A later day already has entries, so this day can no longer be reopened.',
                ]);
            }

            $this->cash->getAccount(CashAccountCode::CanteenCash); // lock

            CashTransaction::where('reference_type', self::ADJUSTMENT_REF)
                ->where('reference_id', $locked->id)
                ->where('transaction_type', 'closing_adjustment')
                ->where('is_reversed', false)
                ->get()
                ->each(function (CashTransaction $tx) use ($locked, $admin, $reason) {
                    $ctx = [
                        'session' => $locked,
                        'reference_type' => self::ADJUSTMENT_REF,
                        'reference_id' => $locked->id,
                        'notes' => 'Reversal of closing adjustment: '.$reason,
                        'user' => $admin,
                        'allow_negative' => true,
                    ];
                    $tx->direction === 'in'
                        ? $this->cash->debit(CashAccountCode::CanteenCash, $tx->amount, 'reversal', $ctx)
                        : $this->cash->credit(CashAccountCode::CanteenCash, $tx->amount, 'reversal', $ctx);
                    $tx->update(['is_reversed' => true]);
                });

            $old = $locked->only(['status', 'expected_closing_cash', 'actual_counted_cash', 'cash_difference', 'difference_reason']);
            $old['status'] = $locked->status->value;

            $locked->update([
                'status' => DailySessionStatus::Open,
                'expected_closing_cash' => null,
                'actual_counted_cash' => null,
                'cash_difference' => null,
                'difference_reason' => null,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $this->activity->log(
                $locked, 'day_reopened', 'Day reopened', $reason, null, null,
                ActivityColor::Orange, null, $locked, false, null, $admin,
            );
            $this->audit->log('daily_session.reopened', $locked, $old, ['status' => 'open'], $reason, $admin);

            return $locked->refresh();
        });
    }

    /** @param  iterable<int, object>  $rows */
    private function sum(iterable $rows, string $column): string
    {
        $total = Money::zero();
        foreach ($rows as $row) {
            $total = Money::add($total, $row->{$column});
        }

        return $total;
    }
}
