<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorLedgerEntry extends Model
{
    protected $fillable = [
        'vendor_id', 'daily_session_id', 'entry_type', 'reference_type', 'reference_id', 'debit',
        'credit', 'balance_after', 'notes', 'created_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
