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

    /** Elevated roles that can manage settings, balances, closing, and corrections. */
    public function isElevated(): bool
    {
        return $this === self::SuperUser || $this === self::Admin;
    }
}
