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
use App\Models\SaleSegment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyClosingService
{
    /** Preset reasons when a sale segment / Rent Side has Rs. 0 for the day. */
    public const EMPTY_FIELD_REASONS = [
        'sale nahi thi',
        'canteen bnd thi',
        'No Cash',
        '0',
    ];

    public const OTHER_REASON = 'other';

    private const ADJUSTMENT_REF = 'closing_adjustment';

    public function __construct(
        private CashLedgerService $cash,
        private ActivityLogService $activity,
        private AuditService $audit,
        private SettingService $settings,
        private SaleService $sales,
    ) {}

    /**
     * Empty sale segments + missing rent that need a reason before the day can close.
     *
     * @return array{
     *   segments: list<array{segment_id: int, name: string}>,
     *   rent_missing: bool,
     *   daily_rent_suggest: string
     * }
     */
    public function checklistGaps(DailySession $session): array
    {
        $salesBySegment = Sale::where('daily_session_id', $session->id)
            ->where('is_reversed', false)
            ->get(['sale_segment_id', 'amount'])
            ->groupBy('sale_segment_id');

        $emptySegments = [];
        foreach (SaleSegment::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $segment) {
            $total = Money::zero();
            foreach ($salesBySegment->get($segment->id, collect()) as $row) {
                $total = Money::add($total, $row->amount);
            }
            if (Money::isZero($total)) {
                $emptySegments[] = [
                    'segment_id' => $segment->id,
                    'name' => $segment->name,
                ];
            }
        }

        // Rent Side is a sale segment now — empty Rent Side pill is enough; no separate rent field.
        return [
            'segments' => $emptySegments,
            'rent_missing' => false,
            'daily_rent_suggest' => $this->settings->getDecimal('daily_rent_suggest', 5000),
        ];
    }

    /**
     * Record sale amounts entered on the day-close screen for empty segments.
     *
     * @param  array<int|string, string|int|float|null>  $amounts
     */
    private function recordCloseScreenSales(DailySession $session, array $amounts, User $user): void
    {
        if ($amounts === []) {
            return;
        }

        $gaps = collect($this->checklistGaps($session)['segments'])->keyBy('segment_id');
        $date = $session->business_date->toDateString();

        foreach ($amounts as $segId => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $amount = Money::of(is_string($raw) ? str_replace([',', ' '], '', trim($raw)) : $raw);
            if (! Money::isPositive($amount)) {
                continue;
            }
            if (! $gaps->has((int) $segId)) {
                continue; // already has a sale / not a gap
            }

            $this->sales->record([
                'sale_segment_id' => (int) $segId,
                'amount' => $amount,
                'payment_method' => 'cash',
                'business_date' => $date,
                'notes' => 'Added on day close',
                'idempotency_key' => 'close-sale-'.$session->id.'-'.$segId.'-'.$amount,
            ], $user);
        }
    }

    /**
     * Validate + normalise close checklist reasons for empty segments / missing rent.
     *
     * @param  array{
     *     segment_reasons?: array<int|string, string>,
     *     segment_reason_other?: array<int|string, string>,
     *     rent_reason?: ?string
     * }  $input
     * @return array{segments: array<string, string>, rent: ?string}
     */
    public function validateChecklist(DailySession $session, array $input): array
    {
        $gaps = $this->checklistGaps($session);
        $errors = [];
        $segmentReasons = [];

        $allowed = self::EMPTY_FIELD_REASONS;

        foreach ($gaps['segments'] as $gap) {
            $id = (string) $gap['segment_id'];

            // Sale amount already recorded for this gap — no reason needed.
            $amountRaw = $input['segment_amounts'][$gap['segment_id']]
                ?? $input['segment_amounts'][$id]
                ?? null;
            if ($amountRaw !== null && $amountRaw !== '' && Money::isPositive(Money::of($amountRaw))) {
                continue;
            }

            $choice = trim((string) ($input['segment_reasons'][$gap['segment_id']]
                ?? $input['segment_reasons'][$id]
                ?? ''));

            if ($choice === self::OTHER_REASON) {
                $reason = trim((string) ($input['segment_reason_other'][$gap['segment_id']]
                    ?? $input['segment_reason_other'][$id]
                    ?? ''));
                if (strlen($reason) < 2) {
                    $errors["segment_reason_other.{$id}"] = "\"{$gap['name']}\" ke liye apni wajah likhein.";
                } else {
                    $segmentReasons[$id] = $reason;
                }
            } elseif ($choice === '' || ! in_array($choice, $allowed, true)) {
                $errors["segment_reasons.{$id}"] = "\"{$gap['name']}\" — sale amount likho ya wajah chunein.";
            } else {
                $segmentReasons[$id] = $choice;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // If empty Rent Side segment got a reason, treat it as rent skip for the ledger note.
        $rentReason = null;
        foreach ($segmentReasons as $segId => $why) {
            $seg = SaleSegment::find((int) $segId);
            if ($seg?->isRentSide()) {
                $rentReason = $why;
                break;
            }
        }

        return [
            'segments' => $segmentReasons,
            'rent' => $rentReason,
        ];
    }

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
        $bikri = $sales->where('payment_method', '!=', 'rent_side');
        $salesTotal = $this->sum($bikri, 'amount');
        $cashSales = $this->sum($bikri->where('payment_method', 'cash'), 'amount');

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
            'sales_count' => $bikri->count(),
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
     * Close the day: empty sale segments / missing rent need reasons; counted cash mismatch needs a reason;
     * then post an adjustment so canteen cash equals counted cash and lock the session.
     *
     * @param  array{
     *     segment_reasons?: array<int|string, string>,
     *     segment_reason_other?: array<int|string, string>,
     *     segment_amounts?: array<int|string, string|int|float|null>,
     *     rent_reason?: ?string
     * }  $checklistInput
     */
    public function close(
        DailySession $session,
        string|int|float $actualCash,
        ?string $reason,
        User $user,
        array $checklistInput = [],
    ): DailySession {
        $actual = Money::of($actualCash);
        if (Money::isNegative($actual)) {
            throw ValidationException::withMessages(['actual_counted_cash' => 'Counted cash cannot be negative.']);
        }
        $reason = $reason !== null ? trim($reason) : null;

        return DB::transaction(function () use ($session, $actual, $reason, $user, $checklistInput) {
            $locked = DailySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DailySessionStatus::Open) {
                throw ValidationException::withMessages(['business_date' => 'This day is already closed.']);
            }

            // Late sales from the close screen (empty segments filled with amount).
            $this->recordCloseScreenSales($locked, $checklistInput['segment_amounts'] ?? [], $user);

            // Re-check gaps under lock after any late sales.
            $checklist = $this->validateChecklist($locked, $checklistInput);

            if ($checklist['rent'] !== null) {
                $alreadySkipped = ActivityLog::where('daily_session_id', $locked->id)
                    ->where('activity_type', 'rent_skip')
                    ->exists();
                if (! $alreadySkipped) {
                    $this->activity->log(
                        $locked, 'rent_skip', 'Rent reserve skipped', $checklist['rent'],
                        null, null, ActivityColor::Blue, null, null, false,
                        ['via' => 'day_close'], $user,
                    );
                }
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
                'close_checklist' => $checklist,
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
                [
                    'rent_reserve' => $balances[CashAccountCode::RentReserve->value],
                    'owner_recoverable' => $balances[CashAccountCode::OwnerRecoverable->value],
                    'checklist' => $checklist,
                ],
                $user,
            );

            $this->audit->log('daily_session.closed', $locked, ['status' => 'open'], [
                'status' => 'closed',
                'expected_closing_cash' => $expected,
                'actual_counted_cash' => $actual,
                'cash_difference' => $difference,
                'close_checklist' => $checklist,
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
                'close_checklist' => null,
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
