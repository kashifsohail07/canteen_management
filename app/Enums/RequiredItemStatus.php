<?php

namespace App\Enums;

enum RequiredItemStatus: string
{
    case Pending = 'pending';
    case Available = 'available';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Needed',
            self::Available => 'Available',
        };
    }
}
