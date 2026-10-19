<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Prevents duplicate submissions (double taps, retries on flaky networks).
 *
 * Usage:
 *   $r = $service->begin($key, $user, 'purchases.store');
 *   if ($r['state'] === IdempotencyService::DUPLICATE) { return stored response from $r['record'] }
 *   ... do work ...
 *   $service->complete($r['record'], 201, $body);   // or $service->forget($r['record']) on failure
 */
class IdempotencyService
{
    public const NEW = 'new';

    public const DUPLICATE = 'duplicate';

    public const IN_PROGRESS = 'in_progress';

    /**
     * @return array{state: string, record: IdempotencyKey}
     */
    public function begin(string $key, User|int $user, string $route): array
    {
        $userId = $user instanceof User ? $user->id : $user;

        try {
            $record = IdempotencyKey::create([
                'key' => $key,
                'user_id' => $userId,
                'route' => $route,
                'created_at' => now(),
            ]);

            return ['state' => self::NEW, 'record' => $record];
        } catch (UniqueConstraintViolationException) {
            $record = IdempotencyKey::where('key', $key)->firstOrFail();

            if ($record->user_id !== $userId || $record->route !== $route) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This idempotency key was already used for a different request.',
                ]);
            }

            return [
                'state' => $record->response_code !== null ? self::DUPLICATE : self::IN_PROGRESS,
                'record' => $record,
            ];
        }
    }

    public function complete(IdempotencyKey $record, int $responseCode, ?array $responseBody = null): IdempotencyKey
    {
        $record->update([
            'response_code' => $responseCode,
            'response_body' => $responseBody,
        ]);

        return $record;
    }

    /** Release a key after a failed request so the client can retry. */
    public function forget(IdempotencyKey $record): void
    {
        if ($record->response_code === null) {
            $record->delete();
        }
    }

    /** Remove keys older than the given number of hours. */
    public function prune(int $hours = 48): int
    {
        return IdempotencyKey::where('created_at', '<', now()->subHours($hours))->delete();
    }
}
