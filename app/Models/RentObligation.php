<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentObligation extends Model
{
    protected $fillable = ['year', 'month', 'target_amount', 'reserved_total', 'paid_total', 'closed_at'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'target_amount' => 'decimal:2',
            'reserved_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RentReservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Human label, e.g. "October 2026". */
    public function periodLabel(): string
    {
        return now()->setDate($this->year, $this->month, 1)->format('F Y');
    }

    /** Amount of the monthly target still unpaid. */
    public function remainingToPay(): string
    {
        $remaining = Money::sub($this->target_amount, $this->paid_total);

        return Money::isNegative($remaining) ? Money::zero() : $remaining;
    }
}
