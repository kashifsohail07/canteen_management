<?php

namespace App\Services;

use App\Enums\CashAccountCode;
use App\Enums\DailySessionStatus;
use App\Enums\DayType;
use App\Models\ActivityLog;
use App\Models\CashTransaction;
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
     *
     * Will not create a newer day while an earlier day is still open.
     * (Viewing an existing empty premature day is handled separately — see openSessionFor.)
     */
    public function getOrCreateForDate(Carbon|string|null $date = null, ?User $user = null): DailySession
    {
        $date = $date === null ? now()->startOfDay() : Carbon::parse($date)->startOfDay();

        $existing = $this->findForDate($date);
        if ($existing) {
            return $existing;
        }

        if ($earlierOpen = $this->latestOpenBefore($date)) {
            throw ValidationException::withMessages([
                'business_date' => 'Pehle '.$earlierOpen->business_date->format('d M Y')
                    .' close karein — us ke baad naya din khulega.',
            ]);
        }

        try {
            return DB::transaction(function () use ($date, $user) {
                if ($earlierOpen = $this->latestOpenBefore($date)) {
                    throw ValidationException::withMessages([
                        'business_date' => 'Pehle '.$earlierOpen->business_date->format('d M Y')
                            .' close karein — us ke baad naya din khulega.',
                    ]);
                }

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

    /** Latest still-open session strictly before the given business date. */
    public function latestOpenBefore(Carbon|string $date): ?DailySession
    {
        return DailySession::query()
            ->where('status', DailySessionStatus::Open->value)
            ->whereDate('business_date', '<', Carbon::parse($date)->toDateString())
            ->orderByDesc('business_date')
            ->first();
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

    /**
     * Session for new entries. If a previous day is still open, entries stay there —
     * an empty premature "today" session is ignored until that earlier day is closed.
     */
    public function openSessionFor(Carbon|string|null $date, ?User $user = null): DailySession
    {
        $date = $date === null ? now()->startOfDay() : Carbon::parse($date)->startOfDay();
        $earlier = $this->latestOpenBefore($date);
        $existing = $this->findForDate($date);

        if ($earlier) {
            // Exact date already in progress with real work → keep using it.
            if ($existing && $existing->isOpen() && ! $this->isEmptySession($existing)) {
                return $this->ensureOpen($existing);
            }

            return $this->ensureOpen($earlier);
        }

        if ($existing) {
            return $this->ensureOpen($existing);
        }

        return $this->ensureOpen($this->getOrCreateForDate($date, $user));
    }

    /** Open session with no activity and no cash movements (premature calendar day). */
    public function isEmptySession(DailySession $session): bool
    {
        if (ActivityLog::where('daily_session_id', $session->id)->exists()) {
            return false;
        }

        return ! CashTransaction::where('daily_session_id', $session->id)->exists();
    }

    /**
     * Remove empty premature open days that were created while an earlier day
     * was still open (so the date picker does not show a fake duplicate day).
     */
    public function discardEmptyPrematureSessions(?Carbon $beforeOrOn = null): int
    {
        $beforeOrOn = ($beforeOrOn ?? now())->startOfDay();
        $deleted = 0;

        $candidates = DailySession::query()
            ->where('status', DailySessionStatus::Open->value)
            ->whereDate('business_date', '<=', $beforeOrOn->toDateString())
            ->orderByDesc('business_date')
            ->get();

        foreach ($candidates as $session) {
            if (! $this->isEmptySession($session)) {
                continue;
            }
            if (! $this->latestOpenBefore($session->business_date)) {
                continue; // empty but nothing earlier open — keep (legitimate new day)
            }
            $session->delete();
            $deleted++;
        }

        return $deleted;
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
