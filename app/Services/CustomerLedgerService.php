<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use App\Services\Concerns\NormalizesContext;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer balance = amount the customer owes the canteen (receivable).
 * debit increases (udhaar), credit decreases (payment / collection).
 *
 * Udhaar does NOT touch sales totals. Payment credits canteen cash.
 */
class CustomerLedgerService
{
    use NormalizesContext;

    public function __construct(
        private AuditService $audit,
        private DailySessionService $sessions,
        private CashLedgerService $cash,
        private ActivityLogService $activity,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     phone?: ?string,
     *     notes?: ?string,
     *     opening_balance?: string|int|float|null
     * }  $data
     */
    public function createCustomer(array $data, ?User $user = null): Customer
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Customer name is required.']);
        }

        $opening = Money::of($data['opening_balance'] ?? 0);

        return DB::transaction(function () use ($data, $name, $opening, $user) {
            if (Customer::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'Is naam ka customer pehle se maujood hai.']);
            }

            $customer = Customer::create([
                'name' => $name,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'current_balance' => Money::zero(),
                'opening_balance' => Money::zero(),
                'opening_balance_locked' => false,
                'is_active' => true,
            ]);

            if (! Money::isZero($opening)) {
                $debit = Money::isPositive($opening) ? $opening : Money::zero();
                $credit = Money::isNegative($opening) ? Money::abs($opening) : Money::zero();
                $this->appendEntry($customer, 'opening_balance', $debit, $credit, [
                    'reference' => $customer,
                    'notes' => Money::isNegative($opening) ? 'Opening advance' : 'Opening baqaya (lena hai)',
                    'user' => $user,
                ]);
                $customer->opening_balance = $opening;
            }

            $customer->opening_balance_locked = true;
            $customer->save();

            return $customer->refresh();
        });
    }

    /**
     * @return Collection<int, Customer>
     */
    public function searchCustomers(?string $query = null, int $limit = 10): Collection
    {
        $query = trim((string) $query);
        $base = Customer::query()->where('is_active', true);

        if ($query === '') {
            return $base
                ->orderByRaw('last_used_at IS NULL')
                ->orderByDesc('last_used_at')
                ->orderBy('name')
                ->limit($limit)
                ->get();
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);

        return $base
            ->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
            ->orderByRaw("CASE WHEN name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$escaped.'%'])
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function lockCustomer(Customer|int $customer): Customer
    {
        $id = $customer instanceof Customer ? $customer->id : $customer;

        $locked = Customer::whereKey($id)->lockForUpdate()->first();
        if (! $locked) {
            throw ValidationException::withMessages(['customer_id' => 'Customer nahi mila.']);
        }

        return $locked;
    }

    /**
     * Record udhaar (customer took items on credit). Does not touch sales or cash.
     *
     * @param  array{notes?: ?string, business_date?: ?string}  $data
     */
    public function recordUdhaar(array $data, User $user): CustomerLedgerEntry
    {
        $amount = $this->positive($data['amount'] ?? 0);
        $notes = trim((string) ($data['notes'] ?? ''));
        if ($notes === '') {
            throw ValidationException::withMessages(['notes' => 'Kya liya tha likho (e.g. 5 rolls).']);
        }

        return DB::transaction(function () use ($data, $user, $amount, $notes) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $customer = $this->lockCustomer((int) ($data['customer_id'] ?? 0));
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'Yeh customer inactive hai.']);
            }

            $now = now();
            $entry = $this->appendEntry($customer, 'udhaar', $amount, Money::zero(), [
                'session' => $session,
                'notes' => $notes,
                'user' => $user,
                'recorded_at' => $now,
            ]);

            $customer->forceFill(['last_used_at' => $now])->save();

            $this->activity->log(
                $session,
                'customer_udhaar',
                $customer->name,
                $notes.' · Balance '.Money::format($customer->current_balance),
                $amount,
                Money::of($customer->current_balance),
                ActivityColor::Orange,
                null,
                $entry,
                false,
                ['customer_id' => $customer->id],
                $user,
                $now,
            );

            return $entry;
        });
    }

    /**
     * Customer pays (clears udhaar). Credits canteen cash so closing cash count includes it.
     *
     * @param  array{notes?: ?string, business_date?: ?string}  $data
     */
    public function recordPayment(array $data, User $user): CustomerLedgerEntry
    {
        $amount = $this->positive($data['amount'] ?? 0);
        $notes = trim((string) ($data['notes'] ?? '')) ?: null;

        return DB::transaction(function () use ($data, $user, $amount, $notes) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $customer = $this->lockCustomer((int) ($data['customer_id'] ?? 0));
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'Yeh customer inactive hai.']);
            }

            $now = now();
            $entry = $this->appendEntry($customer, 'payment', Money::zero(), $amount, [
                'session' => $session,
                'notes' => $notes ?? 'Payment received',
                'user' => $user,
                'recorded_at' => $now,
            ]);

            $this->cash->credit(CashAccountCode::CanteenCash, $amount, 'customer_payment', [
                'session' => $session,
                'reference' => $entry,
                'notes' => 'Customer payment: '.$customer->name,
                'user' => $user,
                'recorded_at' => $now,
            ]);

            $customer->forceFill(['last_used_at' => $now])->save();

            $this->activity->log(
                $session,
                'customer_payment',
                $customer->name,
                ($notes ?: 'Payment').' · Balance '.Money::format($customer->current_balance),
                $amount,
                Money::of($customer->current_balance),
                ActivityColor::Green,
                null,
                $entry,
                false,
                ['customer_id' => $customer->id],
                $user,
                $now,
            );

            return $entry;
        });
    }

    public function adjustBalance(Customer|int $customer, string|int|float $newBalance, string $reason, User $admin): CustomerLedgerEntry
    {
        $this->assertAdmin($admin);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for balance adjustments.']);
        }
        $newBalance = Money::of($newBalance);

        return DB::transaction(function () use ($customer, $newBalance, $reason, $admin) {
            $locked = $this->lockCustomer($customer);
            $old = Money::of($locked->current_balance);
            $delta = Money::sub($newBalance, $old);

            if (Money::isZero($delta)) {
                throw ValidationException::withMessages(['new_balance' => 'The new balance is the same as the current balance.']);
            }

            $entry = $this->appendEntry(
                $locked,
                'adjustment',
                Money::isPositive($delta) ? $delta : Money::zero(),
                Money::isNegative($delta) ? Money::abs($delta) : Money::zero(),
                ['notes' => $reason, 'user' => $admin, 'reference' => $locked],
            );

            $this->audit->log('customer.balance_adjusted', $locked, ['current_balance' => $old], ['current_balance' => $newBalance], $reason, $admin);

            $session = $this->sessions->openSessionFor(null, $admin);
            $this->activity->log(
                $session,
                'customer_balance_adjust',
                'Balance adjust · '.$locked->name,
                'Pehle '.Money::format($old).' → Ab '.Money::format($newBalance).' · '.$reason,
                Money::abs($delta),
                $newBalance,
                ActivityColor::Blue,
                null,
                $entry,
                false,
                [
                    'customer_id' => $locked->id,
                    'old_balance' => Money::format($old),
                    'new_balance' => Money::format($newBalance),
                    'reason' => $reason,
                ],
                $admin,
            );

            return $entry;
        });
    }

    public function setOpeningBalance(Customer|int $customer, string|int|float $newOpening, string $reason, User $admin): CustomerLedgerEntry
    {
        $this->assertAdmin($admin);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to change an opening balance.']);
        }
        $newOpening = Money::of($newOpening);

        return DB::transaction(function () use ($customer, $newOpening, $reason, $admin) {
            $locked = $this->lockCustomer($customer);
            $oldOpening = Money::of($locked->opening_balance);
            $delta = Money::sub($newOpening, $oldOpening);

            if (Money::isZero($delta)) {
                throw ValidationException::withMessages(['opening_balance' => 'The opening balance is unchanged.']);
            }

            $entry = $this->appendEntry(
                $locked,
                'adjustment',
                Money::isPositive($delta) ? $delta : Money::zero(),
                Money::isNegative($delta) ? Money::abs($delta) : Money::zero(),
                ['notes' => 'Opening balance changed: '.$reason, 'user' => $admin, 'reference' => $locked],
            );

            $locked->opening_balance = $newOpening;
            $locked->save();

            $this->audit->log('customer.opening_balance_changed', $locked, ['opening_balance' => $oldOpening], ['opening_balance' => $newOpening], $reason, $admin);

            return $entry;
        });
    }

    private function appendEntry(Customer $customer, string $type, string $debit, string $credit, array $context): CustomerLedgerEntry
    {
        $newBalance = Money::sub(Money::add($customer->current_balance, $debit), $credit);
        $reference = $context['reference'] ?? null;

        $entry = CustomerLedgerEntry::create([
            'customer_id' => $customer->id,
            'daily_session_id' => $this->modelId($context['session'] ?? null),
            'entry_type' => $type,
            'reference_type' => $this->referenceType($reference),
            'reference_id' => $reference?->getKey(),
            'debit' => $debit,
            'credit' => $credit,
            'balance_after' => $newBalance,
            'notes' => $context['notes'] ?? null,
            'created_by' => $this->modelId($context['user'] ?? null),
            'recorded_at' => $this->recordedAt($context['recorded_at'] ?? null),
        ]);

        $customer->current_balance = $newBalance;
        $customer->save();

        return $entry;
    }

    private function positive(string|int|float $amount): string
    {
        $amount = Money::of($amount);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount zero se zyada hona chahiye.']);
        }

        return $amount;
    }

    private function assertAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only an admin can perform this action.');
        }
    }
}
