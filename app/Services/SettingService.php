<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Money;

class SettingService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = Setting::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public function set(string $key, string|int|float|bool|null $value): Setting
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value]
        );
    }

    public function getDecimal(string $key, string|int|float $default = 0): string
    {
        $value = $this->get($key);

        if ($value === null || $value === '' || ! is_numeric(str_replace(',', '', $value))) {
            return Money::of($default);
        }

        return Money::of($value);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
