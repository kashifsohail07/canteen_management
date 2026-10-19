<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerTransaction extends Model
{
    protected $fillable = [
        'daily_session_id', 'type', 'amount', 'vendor_payment_id', 'direction', 'balance_after',
        'notes', 'recorded_at', 'created_by', 'idempotency_key', 'is_reversed',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'recorded_at' => 'datetime',
            'is_reversed' => 'boolean',
        ];
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
