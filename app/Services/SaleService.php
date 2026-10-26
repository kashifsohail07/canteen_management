<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\ActivityLog;
use App\Models\RentReservation;
use App\Models\Sale;
use App\Models\SaleSegment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private DailySessionService $sessions,
        private CashLedgerService $cash,
        private ActivityLogService $activity,
        private AuditService $audit,
        private RentService $rent,
    ) {}

    /**
     * $data keys: sale_segment_id, amount, payment_method ('cash' default), notes, business_date, idempotency_key.
     * Normal segments credit canteen cash. Rent Side segment moves cash into rent reserve (like First Break UX).
     */
    public function record(array $data, User $user): Sale
    {
        if (! empty($data['idempotency_key'])
            && ($existing = Sale::where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing->load('segment');
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Sale amount must be greater than zero.']);
        }

        $segment = SaleSegment::where('is_active', true)->find($data['sale_segment_id'] ?? 0);
        if (! $segment) {
            throw ValidationException::withMessages(['sale_segment_id' => 'Choose a valid sale segment.']);
        }

        if ($segment->isRentSide()) {
            return $this->recordRentSide($data, $user, $amount, $segment);
        }

        $method = strtolower((string) ($data['payment_method'] ?? 'cash')) ?: 'cash';

        return DB::transaction(function () use ($data, $user, $amount, $segment, $method) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $now = now();

            $sale = Sale::create([
                'daily_session_id' => $session->id,
                'sale_segment_id' => $segment->id,
                'amount' => $amount,
                'payment_method' => $method,
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            if ($method === 'cash') {
                $this->cash->credit(CashAccountCode::CanteenCash, $amount, 'sale', [
                    'session' => $session,
                    'reference' => $sale,
                    'notes' => $segment->name,
                    'user' => $user,
                    'recorded_at' => $now,
                ]);
            }

            $this->activity->log(
                $session, 'sale', $segment->name,
                $method === 'cash' ? 'Cash sale' : ucfirst($method).' sale (no cash movement)',
                $amount, null, ActivityColor::Green, null, $sale, false,
                ['segment' => $segment->slug, 'payment_method' => $method], $user, $now,
            );

            return $sale->refresh()->load('segment');
        });
    }

    /** Rent Side pill: same Add Sale UX, money goes canteen_cash → rent_reserve. */
    private function recordRentSide(array $data, User $user, string $amount, SaleSegment $segment): Sale
    {
        return DB::transaction(function () use ($data, $user, $amount, $segment) {
            $session = $this->sessions->openSessionFor($data['business_date'] ?? null, $user);
            $now = now();

            $sale = Sale::create([
                'daily_session_id' => $session->id,
                'sale_segment_id' => $segment->id,
                'amount' => $amount,
                'payment_method' => 'rent_side',
                'notes' => $data['notes'] ?? null,
                'recorded_at' => $now,
                'created_by' => $user->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $reservation = $this->rent->reserve([
                'amount' => $amount,
                'notes' => $data['notes'] ?? ('Via Add Sale – '.$segment->name),
                'business_date' => $data['business_date'] ?? $session->business_date->toDateString(),
                'idempotency_key' => ! empty($data['idempotency_key'])
                    ? $data['idempotency_key'].':rent'
                    : null,
                'sale_id' => $sale->id,
            ], $user);

            // Point the feed entry at the sale so tapping opens the same detail/edit flow.
            $feed = ActivityLog::query()
                ->where('reference_type', 'rent_reservation')
                ->where('reference_id', $reservation->id)
                ->first();

            if ($feed) {
                $feed->update([
                    'activity_type' => 'rent_reserve',
                    'title' => $segment->name,
                    'summary' => 'Rent side rakha (Add Sale)',
                    'color' => ActivityColor::Blue,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'meta' => [
                        'segment' => $segment->slug,
                        'payment_method' => 'rent_side',
                        'rent_reservation_id' => $reservation->id,
                    ],
                ]);
            }

            return $sale->refresh()->load('segment');
        });
    }

    /**
     * Super User only: change segment / amount / notes on an existing sale.
     * Cash drawer is adjusted by the difference; activity feed + audit trail are updated.
     *
     * $data keys: sale_segment_id, amount, notes, reason.
     */
    public function update(Sale $sale, array $data, User $user): Sale
    {
        if (! $user->isSuperUser()) {
            throw new AuthorizationException('Only a super user can edit a sale.');
        }

        if ($sale->is_reversed) {
            throw ValidationException::withMessages(['amount' => 'Yeh sale reverse ho chuki hai — edit nahi ho sakti.']);
        }

        $sale->loadMissing('dailySession', 'segment');

        if ($sale->dailySession->isClosed()) {
            throw ValidationException::withMessages([
                'amount' => 'Pehle din reopen karein, phir sale edit karein.',
            ]);
        }

        $amount = Money::of($data['amount'] ?? 0);
        if (Money::isNegative($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount negative nahi ho sakta.']);
        }

        $segment = SaleSegment::where('is_active', true)->find($data['sale_segment_id'] ?? 0);
        if (! $segment) {
            throw ValidationException::withMessages(['sale_segment_id' => 'Choose a valid sale segment.']);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Edit ki wajah likhna zaroori hai.']);
        }

        // Keep rent-side edits on the rent segment (switching kind is a bigger rewrite).
        if ($sale->segment?->isRentSide() && ! $segment->isRentSide()) {
            throw ValidationException::withMessages([
                'sale_segment_id' => 'Rent Side entry ko doosre segment mein nahi badal sakte.',
            ]);
        }
        if (! $sale->segment?->isRentSide() && $segment->isRentSide()) {
            throw ValidationException::withMessages([
                'sale_segment_id' => 'Sale ko Rent Side mein convert nahi kar sakte — nayi Rent Side entry karein.',
            ]);
        }

        return DB::transaction(function () use ($sale, $data, $user, $amount, $segment, $reason) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $locked->load('dailySession', 'segment');

            if ($locked->is_reversed || $locked->dailySession->isClosed()) {
                throw ValidationException::withMessages(['amount' => 'Yeh sale ab edit nahi ho sakti.']);
            }

            $old = [
                'sale_segment_id' => $locked->sale_segment_id,
                'segment' => $locked->segment->name,
                'amount' => Money::of($locked->amount),
                'notes' => $locked->notes,
                'payment_method' => $locked->payment_method,
            ];

            $method = strtolower((string) $locked->payment_method) ?: 'cash';
            $oldAmount = Money::of($locked->amount);
            $diff = Money::sub($amount, $oldAmount);

            if ($method === 'rent_side' && ! Money::isZero($diff)) {
                $this->adjustRentSideCash($locked, $diff, $reason, $user);
            } elseif ($method === 'cash' && ! Money::isZero($diff)) {
                $ctx = [
                    'session' => $locked->dailySession,
                    'reference' => $locked,
                    'notes' => 'Sale edit: '.$reason,
                    'user' => $user,
                ];
                if (Money::isPositive($diff)) {
                    $this->cash->credit(CashAccountCode::CanteenCash, $diff, 'sale_adjustment', $ctx);
                } else {
                    $this->cash->debit(CashAccountCode::CanteenCash, Money::abs($diff), 'sale_adjustment', $ctx);
                }
            }

            $locked->update([
                'sale_segment_id' => $segment->id,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($method === 'rent_side') {
                RentReservation::query()
                    ->where('sale_id', $locked->id)
                    ->where('is_reversed', false)
                    ->update(['amount' => $amount, 'notes' => $data['notes'] ?? null]);
            }

            $summary = Money::isZero($amount)
                ? 'Ghalti se entry — amount zero'
                : ($method === 'rent_side'
                    ? 'Rent side rakha (Add Sale)'
                    : ($method === 'cash'
                        ? 'Cash sale'
                        : ucfirst($method).' sale (no cash movement)'));

            $title = $segment->name;

            $feed = ActivityLog::query()
                ->where('reference_type', 'sale')
                ->where('reference_id', $locked->id)
                ->first();

            if ($feed) {
                $feed->update([
                    'title' => $title,
                    'summary' => $summary,
                    'amount' => $amount,
                    'meta' => ['segment' => $segment->slug, 'payment_method' => $method, 'edited' => true],
                ]);
            }

            $this->audit->log('sale.updated', $locked, $old, [
                'sale_segment_id' => $segment->id,
                'segment' => $segment->name,
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
                'payment_method' => $method,
            ], $reason, $user);

            return $locked->refresh()->load('segment');
        });
    }

    /**
     * Super User only: remove a mistaken sale from the day.
     * Reverses cash / rent-side effects, marks the sale reversed, drops the activity feed row.
     */
    public function delete(Sale $sale, string $reason, User $user): void
    {
        if (! $user->isSuperUser()) {
            throw new AuthorizationException('Only a super user can delete a sale.');
        }

        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Delete ki wajah likhna zaroori hai.']);
        }

        $sale->loadMissing('dailySession', 'segment');

        if ($sale->is_reversed) {
            throw ValidationException::withMessages(['reason' => 'Yeh sale pehle hi delete / reverse ho chuki hai.']);
        }

        if ($sale->dailySession->isClosed()) {
            throw ValidationException::withMessages([
                'reason' => 'Pehle din reopen karein, phir sale delete karein.',
            ]);
        }

        DB::transaction(function () use ($sale, $reason, $user) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $locked->load('dailySession', 'segment');

            if ($locked->is_reversed || $locked->dailySession->isClosed()) {
                throw ValidationException::withMessages(['reason' => 'Yeh sale ab delete nahi ho sakti.']);
            }

            $amount = Money::of($locked->amount);
            $method = strtolower((string) $locked->payment_method) ?: 'cash';
            $now = now();

            $ctx = [
                'session' => $locked->dailySession,
                'reference' => $locked,
                'notes' => 'Sale delete: '.$reason,
                'user' => $user,
                'recorded_at' => $now,
            ];

            if ($method === 'rent_side' && Money::isPositive($amount)) {
                // Undo rent set-aside: rent_reserve → canteen_cash
                $this->cash->debit(CashAccountCode::RentReserve, $amount, 'reversal', $ctx);
                $this->cash->credit(CashAccountCode::CanteenCash, $amount, 'reversal', $ctx);
                $this->rent->bumpReservedTotal(
                    $locked->dailySession->business_date->year,
                    $locked->dailySession->business_date->month,
                    Money::sub('0', $amount),
                );

                RentReservation::query()
                    ->where('sale_id', $locked->id)
                    ->where('is_reversed', false)
                    ->update(['is_reversed' => true]);
            } elseif ($method === 'cash' && Money::isPositive($amount)) {
                $this->cash->debit(CashAccountCode::CanteenCash, $amount, 'reversal', $ctx);
            }

            $old = [
                'sale_segment_id' => $locked->sale_segment_id,
                'segment' => $locked->segment?->name,
                'amount' => $amount,
                'payment_method' => $method,
                'notes' => $locked->notes,
            ];

            $locked->update([
                'is_reversed' => true,
                'reversed_by' => $user->id,
                'reversed_at' => $now,
                'reversal_reason' => $reason,
            ]);

            ActivityLog::query()
                ->where('reference_type', 'sale')
                ->where('reference_id', $locked->id)
                ->delete();

            $this->audit->log('sale.deleted', $locked, $old, [
                'is_reversed' => true,
                'reversal_reason' => $reason,
            ], $reason, $user);
        });
    }

    /** Move the rent-side difference between canteen cash and rent reserve. */
    private function adjustRentSideCash(Sale $sale, string $diff, string $reason, User $user): void
    {
        $ctx = [
            'session' => $sale->dailySession,
            'reference' => $sale,
            'notes' => 'Rent Side edit: '.$reason,
            'user' => $user,
        ];

        if (Money::isPositive($diff)) {
            // more rent set aside
            $this->cash->debit(CashAccountCode::CanteenCash, $diff, 'rent_reserve_transfer', $ctx);
            $this->cash->credit(CashAccountCode::RentReserve, $diff, 'rent_reserve_transfer', $ctx);
            $this->rent->bumpReservedTotal($sale->dailySession->business_date->year, $sale->dailySession->business_date->month, $diff);
        } else {
            $abs = Money::abs($diff);
            $this->cash->debit(CashAccountCode::RentReserve, $abs, 'rent_reserve_transfer', $ctx);
            $this->cash->credit(CashAccountCode::CanteenCash, $abs, 'rent_reserve_transfer', $ctx);
            $this->rent->bumpReservedTotal($sale->dailySession->business_date->year, $sale->dailySession->business_date->month, Money::sub('0', $abs));
        }
    }
}
