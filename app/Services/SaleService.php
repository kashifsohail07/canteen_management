<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\ActivityLog;
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
    ) {}

    /**
     * $data keys: sale_segment_id, amount, payment_method ('cash' default), notes, business_date, idempotency_key.
     * Cash sales are credited to canteen cash; other methods are recorded without cash movement.
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
                $session, 'sale', 'Sale – '.$segment->name,
                $method === 'cash' ? 'Cash sale' : ucfirst($method).' sale (no cash movement)',
                $amount, null, ActivityColor::Green, null, $sale, false,
                ['segment' => $segment->slug, 'payment_method' => $method], $user, $now,
            );

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

            if ($method === 'cash' && ! Money::isZero($diff)) {
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

            $summary = Money::isZero($amount)
                ? 'Ghalti se entry — amount zero'
                : ($method === 'cash'
                    ? 'Cash sale'
                    : ucfirst($method).' sale (no cash movement)');

            $feed = ActivityLog::query()
                ->where('reference_type', 'sale')
                ->where('reference_id', $locked->id)
                ->first();

            if ($feed) {
                $feed->update([
                    'title' => 'Sale – '.$segment->name,
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
}
