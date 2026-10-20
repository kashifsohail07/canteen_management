<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Models\ActivityLog;
use App\Models\Vendor;
use App\Services\DailySessionService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesBusinessDate;

    public function __construct(
        private DashboardService $dashboard,
        private DailySessionService $sessions,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // first-time setup: admins with no vendors yet are taken to the vendor wizard
        if ($request->boolean('skip_setup')) {
            $request->session()->put('setup_skipped', true);
        }
        if ($user->isAdmin() && ! $request->session()->get('setup_skipped') && ! Vendor::query()->exists()) {
            return redirect()->route('setup.vendors');
        }

        $date = $this->requestedDate($request);
        $session = $this->sessionForView($date, $user);
        $isToday = $date->isToday();

        $summary = $segments = $activity = null;
        $cash = '0.00';

        if ($session) {
            $summary = $this->dashboard->summary($session);
            $segments = $this->dashboard->segmentTotals($session);
            $activity = $this->dashboard->activity($session, 20)->withQueryString();
            $cash = $isToday
                ? $summary['canteen_cash']
                : ($session->actual_counted_cash ?? $summary['expected_closing_cash']);
        }

        return view('dashboard.index', [
            'date' => $date,
            'isToday' => $isToday,
            'session' => $session,
            'summary' => $summary,
            'segments' => $segments,
            'activity' => $activity,
            'cash' => $cash,
            'canEnter' => $session?->isOpen() ?? false,
            'entryDate' => $isToday ? null : $date->toDateString(),
            'topDate' => $date,
            'vendorCount' => Vendor::count(),
        ]);
    }

    /** JSON feed for "Load more": ?date=Y-m-d&page=N */
    public function activity(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $date = $this->requestedDate($request);
        $session = $this->sessions->findForDate($date);

        if (! $session) {
            return response()->json(['ok' => true, 'html' => '', 'has_more' => false, 'next_page' => null, 'total' => 0]);
        }

        $page = $this->dashboard->activity($session, 20);

        $html = $page->getCollection()->map(
            fn (ActivityLog $a) => Blade::render('<x-activity-item :activity="$activity" />', ['activity' => $a])
        )->implode('');

        return response()->json([
            'ok' => true,
            'html' => $html,
            'has_more' => $page->hasMorePages(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    /** Activity detail: JSON for fetch callers, otherwise the full detail page. */
    public function showActivity(Request $request, ActivityLog $activityLog): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'html' => Blade::render('<x-activity-item :activity="$activity" />', ['activity' => $activityLog->load('vendor')]),
                'url' => route('activity.show', $activityLog),
            ]);
        }

        return redirect()->route('activity.show', $activityLog);
    }
}
