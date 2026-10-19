<?php

namespace App\Services;

use App\Enums\ActivityColor;
use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Concerns\NormalizesContext;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    use NormalizesContext;

    public function log(
        DailySession $session,
        string $type,
        string $title,
        ?string $summary = null,
        ?string $amount = null,
        ?string $secondaryAmount = null,
        ActivityColor $color = ActivityColor::Blue,
        Vendor|int|null $vendor = null,
        ?Model $reference = null,
        bool $hasImage = false,
        ?array $meta = null,
        User|int|null $user = null,
        mixed $recordedAt = null,
    ): ActivityLog {
        return ActivityLog::create([
            'daily_session_id' => $session->id,
            'activity_type' => $type,
            'title' => $title,
            'summary' => $summary,
            'amount' => $amount,
            'secondary_amount' => $secondaryAmount,
            'vendor_id' => $this->modelId($vendor),
            'reference_type' => $this->referenceType($reference),
            'reference_id' => $reference?->getKey(),
            'color' => $color,
            'has_image' => $hasImage,
            'meta' => $meta,
            'created_by' => $this->modelId($user),
            'recorded_at' => $this->recordedAt($recordedAt),
        ]);
    }
}
