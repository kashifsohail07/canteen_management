<?php

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait NormalizesContext
{
    protected function modelId(Model|int|string|null $value): ?int
    {
        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        return $value === null ? null : (int) $value;
    }

    protected function referenceType(?Model $model): ?string
    {
        return $model ? Str::snake(class_basename($model)) : null;
    }

    protected function recordedAt(mixed $value): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }
}
