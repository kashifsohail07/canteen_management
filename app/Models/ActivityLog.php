<?php

namespace App\Models;

use App\Enums\ActivityColor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'daily_session_id', 'activity_type', 'title', 'summary', 'amount', 'secondary_amount',
        'vendor_id', 'reference_type', 'reference_id', 'color', 'has_image', 'meta', 'created_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'secondary_amount' => 'decimal:2',
            'color' => ActivityColor::class,
            'has_image' => 'boolean',
            'meta' => 'array',
            'recorded_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
