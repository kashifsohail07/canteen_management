<?php

namespace App\Enums;

enum DayType: string
{
    case Normal = 'normal';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
    case Ptm = 'ptm';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal Day',
            self::Weekend => 'Weekend',
            self::Holiday => 'Holiday',
            self::Ptm => 'PTM',
            self::Special => 'Special Day',
        };
    }
}
