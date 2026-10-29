<?php

namespace App\Http\Controllers\Concerns;

use App\Models\DailySession;
use App\Models\User;
use App\Services\DailySessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

trait ResolvesBusinessDate
{
    /** Business date from ?date= (or business_date); invalid input falls back to today, future dates are clamped. */
    protected function requestedDate(Request $request): Carbon
    {
        $today = now()->startOfDay();
        $raw = $request->query('date') ?? $request->input('business_date');

        if (! is_string($raw) || $raw === '') {
            return $today;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (Throwable) {
            return $today;
        }

        if ($date->format('Y-m-d') !== $raw) {
            return $today;
        }

        return $date->gt($today) ? $today : $date;
    }

    /** Back-dated entry date for quick-entry pages: Y-m-d for past days, null for today. */
    protected function entryDate(Request $request): ?string
    {
        $date = $this->requestedDate($request);

        return $date->isToday() ? null : $date->toDateString();
    }

    /**
     * Session to display for a date.
     * - Exact date lookup only (never show another day's activity under this date).
     * - Today is created on demand only when no earlier day is still open.
     */
    protected function sessionForView(Carbon $date, ?User $user = null): ?DailySession
    {
        $sessions = app(DailySessionService::class);

        if ($date->isToday()) {
            if ($sessions->latestOpenBefore($date)) {
                // Do not open/create today — caller should redirect to the open earlier day.
                return null;
            }

            return $sessions->getOrCreateForDate($date, $user);
        }

        return $sessions->findForDate($date);
    }
}
