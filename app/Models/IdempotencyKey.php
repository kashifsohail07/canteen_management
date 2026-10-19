<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'user_id', 'route', 'response_code', 'response_body', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_code' => 'integer',
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
