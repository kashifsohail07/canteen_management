<?php

namespace App\Models;

use App\Enums\RequiredItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequiredItem extends Model
{
    /** Class options for the create form dropdown. */
    public const CLASS_OPTIONS = [
        'Beginners',
        'Advance',
        'Prep',
        'Class 1',
        'Class 2',
        'Class 3',
        'Class 4',
        'Class 5',
        'Class 6',
        'Class 7',
        'Class 8',
        'Class 9',
        'Class 10',
        '1st Year',
        '2nd Year',
    ];

    protected $fillable = [
        'title', 'class_name', 'notes', 'status', 'quantity_requested', 'unit',
        'quantity_received', 'price', 'fulfilled_at', 'fulfilled_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequiredItemStatus::class,
            'quantity_requested' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'price' => 'decimal:2',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function isPending(): bool
    {
        return $this->status === RequiredItemStatus::Pending;
    }

    public function isAvailable(): bool
    {
        return $this->status === RequiredItemStatus::Available;
    }
}
