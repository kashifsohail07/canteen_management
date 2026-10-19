<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\DailySession;
use App\Models\OwnerTransaction;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\Concerns\NormalizesContext;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tracks money the owner is owed back ("owner recoverable").
 *
 *  - Owner deposit:         canteen cash UP,   recoverable UP
 *  - Owner repayment:       canteen cash DOWN, recoverable DOWN
 *  - Paid from own pocket:  canteen cash UNCHANGED, recoverable UP
 */
class OwnerLedgerService
{
    use NormalizesContext;

    public function __construct(
        private CashLedgerService $cash,
        private DailySessionService $sessions,
        private ActivityLogService $activity,
    ) {}

    /**
     * @param  array{amount: string|int|float, notes?: ?string, business_date?: ?string, idempotency_key?: ?string}  $data
     */
    public function deposit(array $data, User $user): OwnerTransaction
    {
        if ($existing = $this->findByKey($data['idempotency_key'] ?? null)) {
            return $existing;
        }

        $amount = $this->positive($data['amount'] ?? 0);

        return DB::transaction(function () use ($data, $amount, $user) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $now = now();

            $tx = OwnerTransaction::create([
                'daily_session_id' => $session->id,
                'type' => 'cash_deposit',
                'amount' => $amount,
                'direction' => 'increase_recoverable',
                'balance_after' => Money::zero(), // set below
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $ctx = ['session' => $session, 'reference' => $tx, 'notes' => $data['notes'] ?? null, 'user' => $user, 'recorded_at' => $now];
            $this->cash->credit(CashAccountCode::CanteenCash, $amount, 'owner_deposit', $ctx);
            $owner = $this->cash->credit(CashAccountCode::OwnerRecoverable, $amount, 'owner_deposit', $ctx);

            $tx->update(['balance_after' => $owner->balance_after]);

            $this->activity->log(
                $session, 'owner_deposit', 'Owner deposited cash',
                'Owner owes back '.Money::format($owner->balance_after),
                $amount, $owner->balance_after, ActivityColor::Green, null, $tx, false, null, $user, $now,
            );

            return $tx->refresh();
        });
    }

    /**
     * @param  array{amount: string|int|float, notes?: ?string, business_date?: ?string, idempotency_key?: ?string}  $data
     */
    public function repayment(array $data, User $user): OwnerTransaction
    {
        if ($existing = $this->findByKey($data['idempotency_key'] ?? null)) {
            return $existing;
        }

        $amount = $this->positive($data['amount'] ?? 0);

        return DB::transaction(function () use ($data, $amount, $user) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $now = now();

            $tx = OwnerTransaction::create([
                'daily_session_id' => $session->id,
                'type' => 'repayment',
                'amount' => $amount,
                'direction' => 'decrease_recoverable',
                'balance_after' => Money::zero(),
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $ctx = ['session' => $session, 'reference' => $tx, 'notes' => $data['notes'] ?? null, 'user' => $user, 'recorded_at' => $now];

            // lock canteen cash first (global lock order), then owner account
            $this->cash->getAccount(CashAccountCode::CanteenCash);
            $ownerAccount = $this->cash->getAccount(CashAccountCode::OwnerRecoverable);
            if (Money::cmp($amount, $ownerAccount->balance) > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Repayment exceeds the amount owed to the owner ('.Money::format($ownerAccount->balance).').',
                ]);
            }

            $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'owner_repayment', $ctx);
            $owner = $this->cash->debit(CashAccountCode::OwnerRecoverable, $amount, 'owner_repayment', $ctx + ['allow_negative' => false]);

            $tx->update(['balance_after' => $owner->balance_after]);

            $this->activity->log(
                $session, 'owner_repayment', 'Repaid owner',
                'Still owed to owner '.Money::format($owner->balance_after),
                $amount, $owner->balance_after, ActivityColor::Red, null, $tx, false, null, $user, $now,
            );

            return $tx->refresh();
        });
    }

    /**
     * Vendor paid from the owner's own pocket: recoverable goes up, canteen cash is untouched.
     * Must be called inside the transaction that created the VendorPayment.
     */
    public function recordPersonalVendorPayment(VendorPayment $payment, User $user): OwnerTransaction
    {
        return $this->recordPersonalPayment(
            'vendor_payment',
            $payment->amount,
            $payment->dailySession,
            $payment,
            $user,
            $payment->notes,
            $payment->id,
        );
    }

    /**
     * Generic "paid from personal pocket" effect (vendor payment, rent, expense).
     * $type becomes both the owner_transactions.type and the cash_transactions.transaction_type.
     */
    public function recordPersonalPayment(
        string $type,
        string|int|float $amount,
        DailySession $session,
        ?Model $reference,
        User $user,
        ?string $notes = null,
        ?int $vendorPaymentId = null,
    ): OwnerTransaction {
        $amount = $this->positive($amount);

        return DB::transaction(function () use ($type, $amount, $session, $reference, $user, $notes, $vendorPaymentId) {
            $now = now();

            $owner = $this->cash->credit(CashAccountCode::OwnerRecoverable, $amount, $type, [
                'session' => $session,
                'reference' => $reference,
                'notes' => $notes ?? 'Paid from personal pocket',
                'user' => $user,
                'recorded_at' => $now,
            ]);

            return OwnerTransaction::create([
                'daily_session_id' => $session->id,
                'type' => $type,
                'amount' => $amount,
                'vendor_payment_id' => $vendorPaymentId,
                'direction' => 'increase_recoverable',
                'balance_after' => $owner->balance_after,
                'notes' => $notes ?? 'Paid from personal pocket',
                'recorded_at' => $now,
                'created_by' => $user->id,
            ]);
        });
    }

    public function getRecoverableBalance(): string
    {
        return $this->cash->getBalances()[CashAccountCode::OwnerRecoverable->value];
    }

    private function findByKey(?string $key): ?OwnerTransaction
    {
        return $key ? OwnerTransaction::where('idempotency_key', $key)->first() : null;
    }

    private function positive(string|int|float $amount): string
    {
        $amount = Money::of($amount);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return $amount;
    }
}
