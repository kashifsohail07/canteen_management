<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * BCMath based money helper. All values are handled as numeric strings with scale 2.
 */
class Money
{
    public const SCALE = 2;

    /** Normalise any numeric-ish value to a string with 2 decimals. */
    public static function of(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return self::zero();
        }

        if (is_float($value)) {
            $value = number_format($value, self::SCALE + 2, '.', '');
        } elseif (is_string($value)) {
            $value = str_replace([',', ' '], '', trim($value));
        } else {
            $value = (string) $value;
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid money value: {$value}");
        }

        return self::round($value);
    }

    public static function zero(): string
    {
        return '0.00';
    }

    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    /** Multiply (e.g. rate x quantity); the result is rounded half away from zero to 2 decimals. */
    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        $a = is_float($a) ? number_format($a, 6, '.', '') : (string) ($a ?? 0);
        $b = is_float($b) ? number_format($b, 6, '.', '') : (string) ($b ?? 0);
        $a = str_replace(',', '', $a);
        $b = str_replace(',', '', $b);

        return self::round(bcmul($a, $b, 8));
    }

    /** Compare: -1, 0 or 1. */
    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isPositive(string|int|float|null $a): bool
    {
        return self::cmp($a, 0) > 0;
    }

    public static function isZero(string|int|float|null $a): bool
    {
        return self::cmp($a, 0) === 0;
    }

    public static function isNegative(string|int|float|null $a): bool
    {
        return self::cmp($a, 0) < 0;
    }

    public static function abs(string|int|float|null $a): string
    {
        $a = self::of($a);

        return ltrim($a, '-');
    }

    /** Format as "Rs. 14,000" (decimals shown only when non-zero). */
    public static function format(string|int|float|null $value, bool $withCurrency = true): string
    {
        $v = self::of($value);
        $negative = str_starts_with($v, '-');
        $v = ltrim($v, '-');

        [$int, $dec] = explode('.', $v);
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);
        $out = $dec === '00' ? $int : $int.'.'.$dec;

        if ($negative && ! preg_match('/^0(\.0+)?$/', $out)) {
            $out = '-'.$out;
        }

        return ($withCurrency ? 'Rs. ' : '').$out;
    }

    /** Round a numeric string half away from zero to scale 2. */
    private static function round(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        // add half a unit of the last place, then truncate to 2 decimals
        $rounded = bcadd(bcadd($value, '0.005', 10), '0', self::SCALE);
        if ($negative && bccomp($rounded, '0', self::SCALE) !== 0) {
            $rounded = '-'.$rounded;
        }

        return $rounded;
    }
}
