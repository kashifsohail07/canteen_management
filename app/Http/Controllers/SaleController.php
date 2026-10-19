<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\StoreSaleRequest;
use App\Models\SaleSegment;
use App\Services\DailySessionService;
use App\Services\DashboardService;
use App\Services\SaleService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    public function create(Request $request, DailySessionService $sessions, DashboardService $dashboard): View
    {
        $segments = SaleSegment::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $date = $this->requestedDate($request);
        $session = $this->sessionForView($date, $request->user());

        $selected = (int) $request->query('segment', $segments->first()?->id);
        if (! $segments->contains('id', $selected)) {
            $selected = (int) $segments->first()?->id;
        }

        return view('sales.create', [
            'segments' => $segments,
            'selected' => $selected,
            'entryDate' => $this->entryDate($request),
            'date' => $date,
            'session' => $session,
            'totals' => $session ? $dashboard->segmentTotals($session)->keyBy('segment_id') : collect(),
            'topDate' => $date,
        ]);
    }

    public function store(StoreSaleRequest $request, SaleService $sales): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $sale = $sales->record($data, $request->user());
        $message = 'Sale save ho gayi: '.Money::format($sale->amount).' ('.$sale->segment->name.')';

        if ($request->boolean('next') && ! $request->expectsJson()) {
            $ids = SaleSegment::where('is_active', true)->orderBy('sort_order')->orderBy('id')->pluck('id');
            $idx = $ids->search($sale->sale_segment_id);
            $nextId = $ids->get(($idx === false ? -1 : $idx) + 1) ?? $ids->first();

            return redirect()
                ->route('sales.create', array_filter(['segment' => $nextId, 'date' => $data['business_date'] ?? null]))
                ->with('success', $message);
        }

        return $this->succeed($request, $message, $sale->daily_session_id, $data['business_date'] ?? null);
    }
}
