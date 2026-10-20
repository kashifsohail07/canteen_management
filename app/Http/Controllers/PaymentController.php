<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Vendor;
use App\Services\CashLedgerService;
use App\Services\PurchaseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    /** Pay a vendor (payment only, no bill). */
    public function create(Request $request, CashLedgerService $cash): View
    {
        $vendor = $request->filled('vendor') ? Vendor::find($request->query('vendor')) : null;
        $date = $this->requestedDate($request);

        return view('payments.create', [
            'vendor' => $vendor,
            'entryDate' => $this->entryDate($request),
            'topDate' => $date,
            'cashInHand' => $cash->getBalances()['canteen_cash'],
        ]);
    }

    public function store(StorePaymentRequest $request, PurchaseService $purchases): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $payment = $purchases->payVendor($data, $request->user());
        $message = $payment->vendor->name.' ko '.Money::format($payment->amount).' diye. Balance '.Money::format($payment->new_balance);

        return $this->succeed($request, $message, $payment->daily_session_id, $data['business_date'] ?? null);
    }
}
