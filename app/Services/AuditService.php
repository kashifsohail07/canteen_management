<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        User|int|null $user = null,
    ): AuditLog {
        $userId = $user instanceof User ? $user->id : ($user ?? Auth::id());

        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
