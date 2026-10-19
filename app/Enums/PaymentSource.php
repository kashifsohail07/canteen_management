<?php

namespace App\Enums;

enum PaymentSource: string
{
    case CanteenCash = 'canteen_cash';
    case PersonalPocket = 'personal_pocket';

    public function label(): string
    {
        return match ($this) {
            self::CanteenCash => 'Canteen Cash',
            self::PersonalPocket => 'Personal Pocket',
        };
    }
}
