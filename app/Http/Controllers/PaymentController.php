<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\CashLedgerService;
use App\Services\PurchaseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    /**
     * All Vendors hub: list → expand → Add Mall / Add Payment,
     * with previous + today's mall − payment = remaining.
     */
    public function create(Request $request, CashLedgerService $cash): View
    {
        $date = $this->requestedDate($request);
        $session = $this->sessionForView($date, $request->user());
        $selectedId = $request->filled('vendor')
            ? (int) $request->query('vendor')
            : ($request->old('vendor_id') ? (int) $request->old('vendor_id') : null);
        $showPay = $request->boolean('pay')
            || $request->old('amount') !== null
            || $request->old('vendor_id') !== null;

        $vendors = Vendor::query()
            ->where('is_active', true)
            ->orderByDesc('last_used_at')
            ->orderBy('name')
            ->get();

        $todayPurchases = collect();
        $todayPayments = collect();

        if ($session) {
            $todayPurchases = Purchase::with('items')
                ->where('daily_session_id', $session->id)
                ->where('is_reversed', false)
                ->whereIn('vendor_id', $vendors->pluck('id'))
                ->orderByDesc('recorded_at')
                ->get()
                ->groupBy('vendor_id');

            $todayPayments = VendorPayment::query()
                ->where('daily_session_id', $session->id)
                ->where('is_reversed', false)
                ->whereIn('vendor_id', $vendors->pluck('id'))
                ->orderByDesc('recorded_at')
                ->get()
                ->groupBy('vendor_id');
        }

        $cards = $vendors->map(function (Vendor $vendor) use ($todayPurchases, $todayPayments, $selectedId) {
            /** @var Collection<int, Purchase> $purchases */
            $purchases = $todayPurchases->get($vendor->id, collect());
            /** @var Collection<int, VendorPayment> $payments */
            $payments = $todayPayments->get($vendor->id, collect());

            $todayMall = Money::zero();
            foreach ($purchases as $p) {
                $todayMall = Money::add($todayMall, $p->bill_amount);
            }

            $todayPaid = Money::zero();
            foreach ($payments as $p) {
                $todayPaid = Money::add($todayPaid, $p->amount);
            }

            $current = Money::of($vendor->current_balance);
            // Walk back today's effects: purchases increased balance, payments decreased it.
            $previous = Money::add(Money::sub($current, $todayMall), $todayPaid);

            $entries = collect();
            foreach ($purchases as $p) {
                $qty = $p->items->sum(fn ($i) => (float) $i->quantity);
                $entries->push([
                    'kind' => 'mall',
                    'at' => $p->recorded_at,
                    'label' => 'Add Mall',
                    'detail' => $qty > 0
                        ? rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.').' pcs'
                        : 'Bill',
                    'amount' => Money::of($p->bill_amount),
                    'signed' => '+',
                ]);
            }
            foreach ($payments as $p) {
                $entries->push([
                    'kind' => 'payment',
                    'at' => $p->recorded_at,
                    'label' => 'Payment',
                    'detail' => $p->notes ?: 'Paid to vendor',
                    'amount' => Money::of($p->amount),
                    'signed' => '−',
                ]);
            }
            $entries = $entries->sortByDesc(fn ($e) => $e['at']->timestamp)->values();

            return [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'phone' => $vendor->phone,
                'current' => $current,
                'previous' => $previous,
                'today_mall' => $todayMall,
                'today_paid' => $todayPaid,
                'remaining' => $current,
                'entries' => $entries,
                'open' => $selectedId === $vendor->id,
            ];
        });

        // Selected vendor first when deep-linked, then recent-first list order already applied.
        if ($selectedId) {
            $cards = $cards->sortByDesc(fn ($c) => $c['id'] === $selectedId)->values();
        }

        $selected = $selectedId
            ? Vendor::where('is_active', true)->find($selectedId)
            : null;

        $vendorData = $selected ? [
            'id' => $selected->id,
            'name' => $selected->name,
            'balance' => Money::of($selected->current_balance),
            'balance_formatted' => Money::format($selected->current_balance),
            'phone' => $selected->phone,
        ] : null;

        return view('payments.create', [
            'cards' => $cards,
            'vendor' => $selected,
            'vendorData' => $vendorData,
            'showPay' => $showPay && $selected,
            'entryDate' => $this->entryDate($request),
            'topDate' => $date,
            'cashInHand' => $cash->getBalances()['canteen_cash'],
            'dateQuery' => $this->entryDate($request) ? ['date' => $this->entryDate($request)] : [],
        ]);
    }

    public function store(StorePaymentRequest $request, PurchaseService $purchases): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $payment = $purchases->payVendor($data, $request->user());
        $message = 'Paid '.Money::format($payment->amount).' to '.$payment->vendor->name
            .'. Remaining '.Money::format($payment->new_balance);

        if ($request->expectsJson()) {
            return $this->succeed($request, $message, $payment->daily_session_id, $data['business_date'] ?? null);
        }

        return redirect()
            ->route('payments.create', array_filter([
                'vendor' => $payment->vendor_id,
                'date' => $data['business_date'] ?? null,
            ]))
            ->with('success', $message);
    }
}
