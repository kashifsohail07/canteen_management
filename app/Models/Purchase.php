<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'daily_session_id', 'vendor_id', 'entry_mode', 'bill_amount', 'paid_amount', 'payment_source',
        'previous_balance', 'new_balance', 'bill_image_path', 'notes', 'recorded_at', 'created_by',
        'idempotency_key', 'is_reversed', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'bill_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'new_balance' => 'decimal:2',
            'recorded_at' => 'datetime',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
