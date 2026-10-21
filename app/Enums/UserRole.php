<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperUser = 'super_user';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperUser => 'Super User',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    /** Short Urdu/English description for UI. */
    public function description(): string
    {
        return match ($this) {
            self::SuperUser => 'Users manage, sale edit, aur poori app control',
            self::Admin => 'Day close, settings, opening balance, segments',
            self::Staff => 'Roz ka kaam: sale, mall, payment, reports dekhna',
        };
    }

    /** Bullet permissions for roles guide. */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperUser => [
                'Users add / edit / deactivate',
                'Closed day ki sale edit',
                'Admin wali saari permissions',
            ],
            self::Admin => [
                'Day close / reopen',
                'Settings + sale segments',
                'Vendor opening balance / adjust',
                'Sale, mall, payment, rent, owner',
            ],
            self::Staff => [
                'Sale, mall, payment entry',
                'Rent reserve / pay',
                'Reports dekhna',
                'Settings / users / close nahi',
            ],
        };
    }

    /** Elevated roles that can manage settings, balances, closing, and corrections. */
    public function isElevated(): bool
    {
        return $this === self::SuperUser || $this === self::Admin;
    }
}
