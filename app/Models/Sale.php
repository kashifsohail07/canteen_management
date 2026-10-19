<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'daily_session_id', 'sale_segment_id', 'amount', 'payment_method', 'notes', 'recorded_at',
        'created_by', 'idempotency_key', 'is_reversed', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recorded_at' => 'datetime',
            'is_reversed' => 'boolean',
            'reversed_at' => 'datetime',
        ];
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(SaleSegment::class, 'sale_segment_id');
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
