<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use App\Models\DailySession;
use App\Services\DashboardService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

/**
 * Progressive enhancement: normal form posts redirect with a flash message, while requests that
 * ask for JSON get {ok, message, summary, activity_html, activity}.
 */
trait RespondsToForms
{
    protected function succeed(
        Request $request,
        string $message,
        DailySession|int|null $session = null,
        ?string $businessDate = null,
        ?string $redirectTo = null,
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json($this->jsonPayload($message, $session));
        }

        $target = $redirectTo ?? route('dashboard', $businessDate && $businessDate !== now()->toDateString() ? ['date' => $businessDate] : []);

        return redirect($target)->with('success', $message);
    }

    /** @return array<string, mixed> */
    protected function jsonPayload(string $message, DailySession|int|null $session): array
    {
        $session = is_int($session) ? DailySession::find($session) : $session;
        $payload = ['ok' => true, 'message' => $message, 'summary' => null, 'activity_html' => null, 'activity' => null];

        if (! $session) {
            return $payload;
        }

        $summary = app(DashboardService::class)->summary($session);
        foreach (['canteen_cash', 'rent_reserve', 'owner_recoverable', 'vendors_owed', 'customers_owed', 'sales_today', 'purchases_today', 'expenses_today', 'expected_closing_cash'] as $key) {
            $summary[$key.'_formatted'] = Money::format($summary[$key]);
        }
        $payload['summary'] = $summary;

        $latest = ActivityLog::with('vendor')->where('daily_session_id', $session->id)->orderByDesc('id')->first();
        if ($latest) {
            $payload['activity_html'] = Blade::render('<x-activity-item :activity="$activity" />', ['activity' => $latest]);
            $payload['activity'] = [
                'id' => $latest->id,
                'type' => $latest->activity_type,
                'title' => $latest->title,
                'summary' => $latest->summary,
                'amount' => $latest->amount,
                'amount_formatted' => $latest->amount !== null ? Money::format($latest->amount) : null,
                'color' => $latest->color->value,
                'recorded_at' => $latest->recorded_at->toIso8601String(),
            ];
        }

        return $payload;
    }
}
