<?php

namespace App\Services;

use App\Enums\CashAccountCode;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Services\Concerns\NormalizesContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Moves money in/out of cash accounts (canteen_cash, rent_reserve, owner_recoverable).
 *
 * Context keys for credit()/debit(): session, reference (Model), notes, user, recorded_at,
 * idempotency_key, allow_negative (debit only).
 *
 * Lock order used across the app: vendors -> canteen_cash -> rent_reserve -> owner_recoverable.
 */
class CashLedgerService
{
    use NormalizesContext;

    public function __construct(private SettingService $settings) {}

    /** Fetch an account, row-locked by default (call inside a DB transaction). */
    public function getAccount(CashAccountCode|string $code, bool $lock = true): CashAccount
    {
        $code = $code instanceof CashAccountCode ? $code->value : $code;

        $query = CashAccount::where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }

        $account = $query->first();

        if (! $account) {
            $enum = CashAccountCode::tryFrom($code);
            $account = CashAccount::create([
                'code' => $code,
                'name' => $enum?->label() ?? Str::headline($code),
                'balance' => '0.00',
            ]);
        }

        return $account;
    }

    /** Money coming into an account (balance goes up). */
    public function credit(CashAccountCode|string $code, string|int|float $amount, string $transactionType, array $context = []): CashTransaction
    {
        $amount = $this->positiveAmount($amount);

        return DB::transaction(function () use ($code, $amount, $transactionType, $context) {
            $account = $this->getAccount($code);
            $newBalance = Money::add($account->balance, $amount);
            $account->update(['balance' => $newBalance]);

            return $this->record($account, 'in', $amount, $transactionType, $newBalance, $context);
        });
    }

    /** Money going out of an account (balance goes down). */
    public function debit(CashAccountCode|string $code, string|int|float $amount, string $transactionType, array $context = []): CashTransaction
    {
        $amount = $this->positiveAmount($amount);

        return DB::transaction(function () use ($code, $amount, $transactionType, $context) {
            $account = $this->getAccount($code);
            $newBalance = Money::sub($account->balance, $amount);

            $allowNegative = $context['allow_negative'] ?? $this->settings->getBool('allow_negative_cash', false);
            if (Money::isNegative($newBalance) && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'amount' => "Insufficient {$account->name}. Available: ".Money::format($account->balance).'.',
                ]);
            }

            $account->update(['balance' => $newBalance]);

            return $this->record($account, 'out', $amount, $transactionType, $newBalance, $context);
        });
    }

    /** @return array<string, string> code => balance (unlocked read) */
    public function getBalances(): array
    {
        $balances = [];
        foreach (CashAccountCode::cases() as $case) {
            $balances[$case->value] = Money::zero();
        }

        foreach (CashAccount::all() as $account) {
            $balances[$account->code instanceof CashAccountCode ? $account->code->value : $account->code] = Money::of($account->balance);
        }

        return $balances;
    }

    private function positiveAmount(string|int|float $amount): string
    {
        $amount = Money::of($amount);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return $amount;
    }

    private function record(CashAccount $account, string $direction, string $amount, string $type, string $balanceAfter, array $context): CashTransaction
    {
        $reference = $context['reference'] ?? null;

        return CashTransaction::create([
            'cash_account_id' => $account->id,
            'daily_session_id' => $this->modelId($context['session'] ?? null),
            'direction' => $direction,
            'amount' => $amount,
            'transaction_type' => $type,
            'reference_type' => $context['reference_type'] ?? $this->referenceType($reference),
            'reference_id' => $context['reference_id'] ?? $reference?->getKey(),
            'balance_after' => $balanceAfter,
            'notes' => $context['notes'] ?? null,
            'created_by' => $this->modelId($context['user'] ?? null),
            'recorded_at' => $this->recordedAt($context['recorded_at'] ?? null),
            'idempotency_key' => $context['idempotency_key'] ?? null,
        ]);
    }
}
