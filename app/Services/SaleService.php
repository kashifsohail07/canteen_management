<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Enums\CashAccountCode;
use App\Models\Sale;
use App\Models\SaleSegment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private DailySessionService $sessions,
        private CashLedgerService $cash,
        private ActivityLogService $activity,
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
}
