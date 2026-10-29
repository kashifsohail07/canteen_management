<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\DeleteSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\ActivityLog;
use App\Models\Sale;
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

        $selected = $request->filled('segment') ? (int) $request->query('segment') : null;
        if ($selected !== null && ! $segments->contains('id', $selected)) {
            $selected = null;
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
        $message = $sale->segment?->isRentSide()
            ? 'Rent Side rakh diya: '.Money::format($sale->amount)
            : 'Sale save ho gayi: '.Money::format($sale->amount).' ('.$sale->segment->name.')';

        return $this->succeed($request, $message, $sale->daily_session_id, $data['business_date'] ?? null);
    }

    /** Super User: update an existing sale (segment / amount / notes). */
    public function update(UpdateSaleRequest $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        $updated = $sales->update($sale, $request->validated(), $request->user());

        $activity = ActivityLog::query()
            ->where('reference_type', 'sale')
            ->where('reference_id', $updated->id)
            ->first();

        return redirect()
            ->route($activity ? 'activity.show' : 'dashboard', $activity?->id)
            ->with('success', 'Sale update ho gayi: '.Money::format($updated->amount).' ('.$updated->segment->name.')');
    }

    /** Super User: delete a mistaken sale (reverses cash, removes from feed). */
    public function destroy(DeleteSaleRequest $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        $sale->loadMissing('dailySession', 'segment');
        $date = $sale->dailySession?->business_date;
        $label = Money::format($sale->amount).' ('.$sale->segment?->name.')';

        $sales->delete($sale, $request->validated('reason'), $request->user());

        $params = ($date && ! $date->isToday()) ? ['date' => $date->toDateString()] : [];

        return redirect()
            ->route('dashboard', $params)
            ->with('success', 'Sale delete ho gayi: '.$label);
    }
}
