<?php

namespace App\Support;

use App\Models\SchoolCalendar;
use App\Services\SettingService;
use Illuminate\Support\Carbon;

/** Canteen working-day helpers. Weekends (default Sat+Sun) are closed. */
class WorkingDays
{
    /** ISO weekdays that are closed: 6 = Saturday, 7 = Sunday. */
    public static function weekendIsos(?SettingService $settings = null): array
    {
        $settings ??= app(SettingService::class);
        $raw = $settings->get('weekend_days', '6,7') ?? '6,7';
        $days = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw)),
            fn (int $d) => $d >= 1 && $d <= 7,
        )));

        return $days !== [] ? $days : [6, 7];
    }

    public static function isWeekend(Carbon|string $date, ?SettingService $settings = null): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return in_array($date->dayOfWeekIso, self::weekendIsos($settings), true);
    }

    /**
     * Closed for normal canteen work: weekend day, unless school calendar marks it working
     * (e.g. PTM / special Sunday).
     */
    public static function isClosed(Carbon|string $date, ?SettingService $settings = null): bool
    {
        $date = Carbon::parse($date)->startOfDay();
        if (! self::isWeekend($date, $settings)) {
            return false;
        }

        $calendar = SchoolCalendar::whereDate('date', $date->toDateString())->first();

        return ! ($calendar && $calendar->is_working_day);
    }

    public static function previous(Carbon|string $date, ?SettingService $settings = null): Carbon
    {
        $cursor = Carbon::parse($date)->startOfDay()->subDay();
        while (self::isClosed($cursor, $settings)) {
            $cursor->subDay();
        }

        return $cursor;
    }

    public static function next(Carbon|string $date, ?SettingService $settings = null): Carbon
    {
        $cursor = Carbon::parse($date)->startOfDay()->addDay();
        while (self::isClosed($cursor, $settings)) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /** Snap to this date if open, otherwise the previous open day. */
    public static function onOrBefore(Carbon|string $date, ?SettingService $settings = null): Carbon
    {
        $date = Carbon::parse($date)->startOfDay();
        if (! self::isClosed($date, $settings)) {
            return $date;
        }

        return self::previous($date, $settings);
    }

    /** Count Mon–Fri (or non-closed) days in a calendar month. */
    public static function countInMonth(int $year, int $month, ?SettingService $settings = null): int
    {
        $cursor = Carbon::create($year, $month, 1)->startOfDay();
        $end = $cursor->copy()->endOfMonth()->startOfDay();
        $count = 0;
        while ($cursor->lte($end)) {
            if (! self::isClosed($cursor, $settings)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
