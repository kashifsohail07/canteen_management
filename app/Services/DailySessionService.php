<?php

namespace App\Services;

use App\Enums\CashAccountCode;
use App\Enums\DailySessionStatus;
use App\Enums\DayType;
use App\Models\DailySession;
use App\Models\SchoolCalendar;
use App\Models\User;
use App\Services\Concerns\NormalizesContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailySessionService
{
    use NormalizesContext;

    public function __construct(
        private CashLedgerService $cash,
        private SettingService $settings,
    ) {}

    /**
     * Find the session for a business date or create it, carrying forward opening balances.
     */
    public function getOrCreateForDate(Carbon|string|null $date = null, ?User $user = null): DailySession
    {
        $date = $date === null ? now()->startOfDay() : Carbon::parse($date)->startOfDay();

        $existing = $this->findForDate($date);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($date, $user) {
                // another request may have created it while we waited
                if ($existing = $this->findForDate($date)) {
                    return $existing;
                }

                $previous = $this->getPreviousClosed($date);
                $balances = $this->cash->getBalances();

                $openingCanteen = $previous?->actual_counted_cash
                    ?? $balances[CashAccountCode::CanteenCash->value];

                return DailySession::create([
                    'business_date' => $date->toDateString(),
                    'day_type' => $this->detectDayType($date),
                    'status' => DailySessionStatus::Open,
                    'opening_canteen_cash' => $openingCanteen,
                    'opening_rent_reserve' => $balances[CashAccountCode::RentReserve->value],
                    'opening_owner_recoverable' => $balances[CashAccountCode::OwnerRecoverable->value],
                    'created_by' => $user?->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->findForDate($date) ?? throw new \RuntimeException('Could not create daily session.');
        }
    }

    public function findForDate(Carbon|string $date): ?DailySession
    {
        return DailySession::whereDate('business_date', Carbon::parse($date)->toDateString())->first();
    }

    /** Throws a validation error if the session has been closed. */
    public function ensureOpen(DailySession $session): DailySession
    {
        if ($session->fresh()->status !== DailySessionStatus::Open) {
            throw ValidationException::withMessages([
                'business_date' => 'This day ('.$session->business_date->format('d M Y').') is closed. Entries cannot be added.',
            ]);
        }

        return $session;
    }

    /** Convenience: session for date, guaranteed open. */
    public function openSessionFor(Carbon|string|null $date, ?User $user = null): DailySession
    {
        return $this->ensureOpen($this->getOrCreateForDate($date, $user));
    }

    /** Most recent closed session before the given date. */
    public function getPreviousClosed(Carbon|string $date): ?DailySession
    {
        return DailySession::where('status', DailySessionStatus::Closed->value)
            ->whereDate('business_date', '<', Carbon::parse($date)->toDateString())
            ->orderByDesc('business_date')
            ->first();
    }

    private function detectDayType(Carbon $date): DayType
    {
        $calendar = SchoolCalendar::whereDate('date', $date->toDateString())->first();
        if ($calendar && DayType::tryFrom($calendar->day_type)) {
            return DayType::from($calendar->day_type);
        }

        // ISO weekday numbers (1=Mon ... 7=Sun) configured as comma list; default Sunday only
        $weekend = array_filter(array_map('intval', explode(',', $this->settings->get('weekend_days', '7') ?? '7')));
        if (in_array($date->dayOfWeekIso, $weekend, true)) {
            return DayType::Weekend;
        }

        return DayType::Normal;
    }
}
