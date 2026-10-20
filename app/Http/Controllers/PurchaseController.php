<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Vendor;
use App\Services\DailySessionService;
use App\Services\PurchaseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    /** Quick Maal entry. */
    public function create(Request $request): View
    {
        $vendor = $request->filled('vendor') ? Vendor::where('is_active', true)->find($request->query('vendor')) : null;
        $date = $this->requestedDate($request);

        return view('purchases.create', [
            'vendor' => $vendor,
            'entryDate' => $this->entryDate($request),
            'topDate' => $date,
        ]);
    }

    /**
     * Aaj is vendor se pehle kitni deliveries aa chuki — Maal Entry screen ke liye.
     */
    public function todayDeliveries(Request $request, DailySessionService $sessions): JsonResponse
    {
        $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date')
            ? \Illuminate\Support\Carbon::parse($request->query('date'))->toDateString()
            : now()->toDateString();

        $session = $sessions->findForDate($date);
        if (! $session) {
            return response()->json(['lines' => [], 'last_rate' => null, 'last_item_name' => null]);
        }

        $purchases = Purchase::with('items')
            ->where('daily_session_id', $session->id)
            ->where('vendor_id', (int) $request->query('vendor_id'))
            ->where('is_reversed', false)
            ->orderBy('recorded_at')
            ->get();

        $lines = [];
        $lastRate = null;
        $lastItemName = null;

        foreach ($purchases as $purchase) {
            if ($purchase->items->isNotEmpty()) {
                foreach ($purchase->items as $item) {
                    $qty = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') ?: '0';
                    $lines[] = [
                        'time' => $purchase->recorded_at->timezone(config('app.timezone'))->format('g:i A'),
                        'item_name' => $item->item_name,
                        'quantity' => $qty,
                        'unit' => $item->unit,
                        'rate' => Money::of($item->rate),
                        'line_total' => Money::of($item->line_total),
                    ];
                    $lastRate = Money::of($item->rate);
                    $lastItemName = $item->item_name;
                }
            } else {
                $lines[] = [
                    'time' => $purchase->recorded_at->timezone(config('app.timezone'))->format('g:i A'),
                    'item_name' => 'Total bill',
                    'quantity' => '1.00',
                    'unit' => '',
                    'rate' => Money::of($purchase->bill_amount),
                    'line_total' => Money::of($purchase->bill_amount),
                ];
            }
        }

        return response()->json([
            'lines' => $lines,
            'last_rate' => $lastRate,
            'last_item_name' => $lastItemName,
        ]);
    }

    public function store(StorePurchaseRequest $request, PurchaseService $purchases): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['bill_image'] = $request->file('bill_image');
        // UI defaults to itemized; API/tests that only send bill_amount stay on total.
        $data['entry_mode'] = $data['entry_mode']
            ?? (! empty($data['items']) ? 'itemized' : 'total');

        $purchase = $purchases->store($data, $request->user());

        $qtyNote = '';
        if ($purchase->relationLoaded('items') ? $purchase->items->isNotEmpty() : $purchase->items()->exists()) {
            $purchase->loadMissing('items');
            $qty = $purchase->items->sum(fn (PurchaseItem $i) => (float) $i->quantity);
            if ($qty > 0) {
                $qtyNote = ' · Qty '.rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            }
        }

        $message = 'Maal entry save: '.$purchase->vendor->name.$qtyNote
            .' – Bill '.Money::format($purchase->bill_amount)
            .'. Naya balance '.Money::format($purchase->new_balance);

        if ($request->boolean('next') && ! $request->expectsJson()) {
            return redirect()->route('purchases.create', array_filter([
                'date' => $data['business_date'] ?? null,
                'vendor' => $purchase->vendor_id,
            ]))->with('success', $message);
        }

        return $this->succeed($request, $message, $purchase->daily_session_id, $data['business_date'] ?? null);
    }
}
