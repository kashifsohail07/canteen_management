<?php

namespace App\Enums;

enum CashAccountCode: string
{
    case CanteenCash = 'canteen_cash';
    case RentReserve = 'rent_reserve';
    case OwnerRecoverable = 'owner_recoverable';

    public function label(): string
    {
        return match ($this) {
            self::CanteenCash => 'Canteen Cash',
            self::RentReserve => 'Rent Reserve',
            self::OwnerRecoverable => 'Owner Recoverable',
        };
    }
}
