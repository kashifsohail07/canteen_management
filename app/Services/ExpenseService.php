<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Enums\PaymentSource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private DailySessionService $sessions,
        private CashLedgerService $cash,
        private OwnerLedgerService $owner,
        private ActivityLogService $activity,
    ) {}

    /** $data keys: amount, expense_category_id?, payment_source (canteen_cash|personal_pocket), notes, business_date, idempotency_key. */
    public function record(array $data, User $user): Expense
    {
        if (! empty($data['idempotency_key'])
            && ($existing = Expense::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing->load('category');
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Expense amount must be greater than zero.']);
        }

        $source = PaymentSource::tryFrom($data['payment_source'] ?? PaymentSource::CanteenCash->value);
        if (! $source) {
            throw ValidationException::withMessages(['payment_source' => 'Invalid payment source.']);
        }

        $category = null;
        if (! empty($data['expense_category_id'])) {
            $category = ExpenseCategory::find($data['expense_category_id']);
            if (! $category) {
                throw ValidationException::withMessages(['expense_category_id' => 'Invalid expense category.']);
            }
        }

        return DB::transaction(function () use ($data, $user, $amount, $source, $category) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $now = now();

            $expense = Expense::create([
                'daily_session_id' => $session->id,
                'expense_category_id' => $category?->id,
                'amount' => $amount,
                'payment_source' => $source->value,
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            if ($source === PaymentSource::CanteenCash) {
                $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'expense', [
                    'session' => $session,
                    'reference' => $expense,
                    'notes' => $category?->name,
                    'user' => $user,
                    'recorded_at' => $now,
                ]);
            } else {
                $this->owner->recordPersonalPayment('expense', $amount, $session, $expense, $user, $data['notes'] ?? null);
            }

            $this->activity->log(
                $session, 'expense', 'Expense'.($category ? ' – '.$category->name : ''),
                $data['notes'] ?? ($source === PaymentSource::PersonalPocket ? 'From personal pocket' : 'From canteen cash'),
                $amount, null, ActivityColor::Red, null, $expense, false, null, $user, $now,
            );

            return $expense->refresh()->load('category');
        });
    }
}
