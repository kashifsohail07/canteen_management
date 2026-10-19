<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\RentObligation;
use App\Models\RentPayment;
use App\Models\RentReservation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentService
{
    public const SOURCES = ['rent_reserve', 'personal_pocket', 'canteen_cash'];

    public function __construct(
        private DailySessionService $sessions,
        private CashLedgerService $cash,
        private OwnerLedgerService $owner,
        private SettingService $settings,
        private ActivityLogService $activity,
    ) {}

    public function ensureObligation(int $year, int $month): RentObligation
    {
        $find = fn () => RentObligation::where('year', $year)->where('month', $month)->first();

        if ($existing = $find()) {
            return $existing;
        }

        try {
            return RentObligation::create([
                'year' => $year,
                'month' => $month,
                'target_amount' => $this->settings->getDecimal('monthly_rent_target', 71000),
                'reserved_total' => Money::zero(),
                'paid_total' => Money::zero(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $find();
        }
    }

    /**
     * Set money aside for rent: internal transfer canteen_cash -> rent_reserve.
     * $data keys: amount (defaults to setting daily_rent_suggest), notes, business_date, idempotency_key.
     */
    public function reserve(array $data, User $user): RentReservation
    {
        if (! empty($data['idempotency_key'])
            && ($existing = RentReservation::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }

        $amount = Money::of($data['amount'] ?? $this->settings->getDecimal('daily_rent_suggest', 5000));
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Reserve amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($data, $user, $amount) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $obligation = $this->lockObligation($session->business_date->year, $session->business_date->month);
            $now = now();

            $reservation = RentReservation::create([
                'daily_session_id' => $session->id,
                'rent_obligation_id' => $obligation->id,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $ctx = ['session' => $session, 'reference' => $reservation, 'notes' => $data['notes'] ?? null, 'user' => $user, 'recorded_at' => $now];
            $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'rent_reserve_transfer', $ctx);
            $reserve = $this->cash->credit(CashAccountCode::RentReserve, $amount, 'rent_reserve_transfer', $ctx);

            $obligation->update(['reserved_total' => Money::add($obligation->reserved_total, $amount)]);

            $this->activity->log(
                $session, 'rent_reserve', 'Rent set aside',
                'Reserve now '.Money::format($reserve->balance_after).' · Month target '.Money::format($obligation->target_amount),
                $amount, $reserve->balance_after, ActivityColor::Blue, null, $reservation, false, null, $user, $now,
            );

            return $reservation->refresh();
        });
    }

    /** Skip today's rent reserve: no money moves. Optionally leaves a note in the activity feed. */
    public function skip(User $user, ?string $note = null, ?string $businessDate = null, bool $log = false): void
    {
        if (! $log) {
            return;
        }

        $session = $this->sessions->openSessionFor($businessDate, $user);
        $this->activity->log(
            $session, 'rent_skip', 'Rent reserve skipped', $note, null, null,
            ActivityColor::Blue, null, null, false, null, $user,
        );
    }

    /**
     * Pay rent. payment_source: rent_reserve (reserve goes down), canteen_cash (cash goes down),
     * personal_pocket (owner recoverable goes up, nothing else moves).
     * $data keys: amount, payment_source, notes, business_date, year?, month?, idempotency_key.
     */
    public function payRent(array $data, User $user): RentPayment
    {
        if (! empty($data['idempotency_key'])
            && ($existing = RentPayment::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Rent amount must be greater than zero.']);
        }

        $source = $data['payment_source'] ?? 'rent_reserve';
        if (! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages(['payment_source' => 'Invalid payment source.']);
        }

        return DB::transaction(function () use ($data, $user, $amount, $source) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $obligation = $this->lockObligation(
                (int) ($data['year'] ?? $session->business_date->year),
                (int) ($data['month'] ?? $session->business_date->month),
            );
            $now = now();

            $payment = RentPayment::create([
                'rent_obligation_id' => $obligation->id,
                'daily_session_id' => $session->id,
                'amount' => $amount,
                'payment_source' => $source,
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $ctx = ['session' => $session, 'reference' => $payment, 'notes' => $data['notes'] ?? null, 'user' => $user, 'recorded_at' => $now];

            match ($source) {
                'rent_reserve' => $this->cash->debit(CashAccountCode::RentReserve, $amount, 'rent_payment', $ctx),
                'canteen_cash' => $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'rent_payment', $ctx),
                'personal_pocket' => $this->owner->recordPersonalPayment('rent_payment', $amount, $session, $payment, $user, $data['notes'] ?? null),
            };

            $obligation->update(['paid_total' => Money::add($obligation->paid_total, $amount)]);

            $this->activity->log(
                $session, 'rent_payment', 'Rent paid',
                'Paid this month '.Money::format($obligation->paid_total).' of '.Money::format($obligation->target_amount)
                    .' · from '.str_replace('_', ' ', $source),
                $amount, $obligation->remainingToPay(), ActivityColor::Red, null, $payment, false,
                ['payment_source' => $source], $user, $now,
            );

            return $payment->refresh();
        });
    }

    private function lockObligation(int $year, int $month): RentObligation
    {
        $obligation = $this->ensureObligation($year, $month);

        return RentObligation::whereKey($obligation->id)->lockForUpdate()->firstOrFail();
    }
}
