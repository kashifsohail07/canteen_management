<?php

namespace App\Models;

use App\Enums\DailySessionStatus;
use App\Enums\DayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySession extends Model
{
    protected $fillable = [
        'business_date', 'day_type', 'status', 'opening_canteen_cash', 'opening_rent_reserve',
        'opening_owner_recoverable', 'expected_closing_cash', 'actual_counted_cash',
        'cash_difference', 'difference_reason', 'close_checklist', 'closed_at', 'closed_by', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'day_type' => DayType::class,
            'status' => DailySessionStatus::class,
            'opening_canteen_cash' => 'decimal:2',
            'opening_rent_reserve' => 'decimal:2',
            'opening_owner_recoverable' => 'decimal:2',
            'expected_closing_cash' => 'decimal:2',
            'actual_counted_cash' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'close_checklist' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === DailySessionStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === DailySessionStatus::Closed;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function vendorPayments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function rentReservations(): HasMany
    {
        return $this->hasMany(RentReservation::class);
    }

    public function rentPayments(): HasMany
    {
        return $this->hasMany(RentPayment::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function ownerTransactions(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
