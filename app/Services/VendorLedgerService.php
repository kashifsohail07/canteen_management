<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\Concerns\NormalizesContext;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor balance = amount the canteen owes the vendor.
 * debit increases the balance (purchase on credit), credit decreases it (payment).
 *
 * recordPurchaseEffect()/recordPaymentEffect() expect a Vendor obtained via lockVendor()
 * inside an open DB transaction; they update that same instance.
 *
 * Context keys: session, reference (Model), notes, user, recorded_at.
 */
class VendorLedgerService
{
    use NormalizesContext;

    public function __construct(private AuditService $audit) {}

    /**
     * @param  array{
     *     name: string,
     *     phone?: ?string,
     *     notes?: ?string,
     *     opening_balance?: string|int|float|null,
     *     default_item_name?: ?string,
     *     default_unit?: ?string,
     *     default_rate?: string|int|float|null
     * }  $data
     */
    public function createVendor(array $data, ?User $user = null): Vendor
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Vendor name is required.']);
        }

        $opening = Money::of($data['opening_balance'] ?? 0);
        // Positive = owed to vendor; negative = advance / overpaid.
        $rate = isset($data['default_rate']) && $data['default_rate'] !== null && $data['default_rate'] !== ''
            ? Money::of($data['default_rate'])
            : null;
        if ($rate !== null && ! Money::isPositive($rate)) {
            $rate = null;
        }

        return DB::transaction(function () use ($data, $name, $opening, $user, $rate) {
            if (Vendor::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => 'A vendor with this name already exists.']);
            }

            $vendor = Vendor::create([
                'name' => $name,
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'default_item_name' => $rate ? ($data['default_item_name'] ?? $name) : null,
                'default_unit' => $rate ? ($data['default_unit'] ?? 'pcs') : null,
                'default_rate' => $rate,
                'current_balance' => Money::zero(),
                'opening_balance' => Money::zero(),
                'opening_balance_locked' => false,
                'is_active' => true,
            ]);

            if (! Money::isZero($opening)) {
                $debit = Money::isPositive($opening) ? $opening : Money::zero();
                $credit = Money::isNegative($opening) ? Money::abs($opening) : Money::zero();
                $this->appendEntry($vendor, 'opening_balance', $debit, $credit, [
                    'reference' => $vendor,
                    'notes' => Money::isNegative($opening) ? 'Opening advance / overpaid' : 'Opening balance',
                    'user' => $user,
                ]);
                $vendor->opening_balance = $opening;
            }

            // After creation only an admin may change the opening balance
            $vendor->opening_balance_locked = true;
            $vendor->save();

            return $vendor->refresh();
        });
    }

    /**
     * Autocomplete: prefix matches first, then contains; recently used vendors when query is empty.
     *
     * @return Collection<int, Vendor>
     */
    public function searchVendors(?string $query = null, int $limit = 10): Collection
    {
        $query = trim((string) $query);
        $base = Vendor::query()->where('is_active', true);

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

    public function getBalance(Vendor|int $vendor): string
    {
        $id = $vendor instanceof Vendor ? $vendor->id : $vendor;

        return Money::of(Vendor::withTrashed()->whereKey($id)->value('current_balance'));
    }

    /** Row-lock a vendor (call inside a DB transaction). */
    public function lockVendor(Vendor|int $vendor): Vendor
    {
        $id = $vendor instanceof Vendor ? $vendor->id : $vendor;

        $locked = Vendor::whereKey($id)->lockForUpdate()->first();
        if (! $locked) {
            throw ValidationException::withMessages(['vendor_id' => 'Vendor not found.']);
        }

        return $locked;
    }

    /** A purchase on the books: vendor balance goes UP (debit). */
    public function recordPurchaseEffect(Vendor $vendor, string|int|float $amount, array $context = []): VendorLedgerEntry
    {
        return $this->appendEntry($vendor, 'purchase', $this->positive($amount), Money::zero(), $context);
    }

    /** A payment to the vendor: vendor balance goes DOWN (credit). */
    public function recordPaymentEffect(Vendor $vendor, string|int|float $amount, array $context = []): VendorLedgerEntry
    {
        return $this->appendEntry($vendor, 'payment', Money::zero(), $this->positive($amount), $context);
    }

    /**
     * Admin-only: force the vendor balance to a new value with a mandatory reason.
     */
    public function adjustBalance(Vendor|int $vendor, string|int|float $newBalance, string $reason, User $admin, array $context = []): VendorLedgerEntry
    {
        $this->assertAdmin($admin);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for balance adjustments.']);
        }
        $newBalance = Money::of($newBalance);

        return DB::transaction(function () use ($vendor, $newBalance, $reason, $admin, $context) {
            $locked = $this->lockVendor($vendor);
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
                array_merge($context, ['notes' => $reason, 'user' => $admin, 'reference' => $locked]),
            );

            $this->audit->log('vendor.balance_adjusted', $locked, ['current_balance' => $old], ['current_balance' => $newBalance], $reason, $admin);

            return $entry;
        });
    }

    /** Admin-only: change a vendor's (locked) opening balance; current balance moves by the same difference. */
    public function setOpeningBalance(Vendor|int $vendor, string|int|float $newOpening, string $reason, User $admin): VendorLedgerEntry
    {
        $this->assertAdmin($admin);
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to change an opening balance.']);
        }
        $newOpening = Money::of($newOpening);
        // Negative opening = advance / overpaid — allowed.

        return DB::transaction(function () use ($vendor, $newOpening, $reason, $admin) {
            $locked = $this->lockVendor($vendor);
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

            $this->audit->log('vendor.opening_balance_changed', $locked, ['opening_balance' => $oldOpening], ['opening_balance' => $newOpening], $reason, $admin);

            return $entry;
        });
    }

    private function appendEntry(Vendor $vendor, string $type, string $debit, string $credit, array $context): VendorLedgerEntry
    {
        $newBalance = Money::sub(Money::add($vendor->current_balance, $debit), $credit);
        $reference = $context['reference'] ?? null;

        $entry = VendorLedgerEntry::create([
            'vendor_id' => $vendor->id,
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

        $vendor->current_balance = $newBalance;
        $vendor->save();

        return $entry;
    }

    private function positive(string|int|float $amount): string
    {
        $amount = Money::of($amount);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
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
