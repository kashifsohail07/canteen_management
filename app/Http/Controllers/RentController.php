<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\PayRentRequest;
use App\Http\Requests\ReserveRentRequest;
use App\Http\Requests\SkipRentRequest;
use App\Services\CashLedgerService;
use App\Services\DailySessionService;
use App\Services\RentService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    public function __construct(
        private RentService $rent,
        private SettingService $settings,
        private CashLedgerService $cash,
        private DailySessionService $sessions,
    ) {}

    /** Reserve (set aside) rent from today's cash, or skip today. */
    public function create(Request $request): View
    {
        $date = $this->requestedDate($request);
        $obligation = $this->rent->ensureObligation($date->year, $date->month);
        $balances = $this->cash->getBalances();

        return view('rent.create', [
            'obligation' => $obligation,
            'suggest' => $this->settings->getDecimal('daily_rent_suggest', 5000),
            'reserve' => $balances['rent_reserve'],
            'cashInHand' => $balances['canteen_cash'],
            'entryDate' => $this->entryDate($request),
            'topDate' => $date,
        ]);
    }

    public function storeReserve(ReserveRentRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reservation = $this->rent->reserve($data, $request->user());

        return $this->succeed(
            $request,
            'Rent side rakh diya: '.Money::format($reservation->amount),
            $reservation->daily_session_id,
            $data['business_date'] ?? null,
        );
    }

    public function skip(SkipRentRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $this->rent->skip($request->user(), $data['notes'] ?? null, $data['business_date'] ?? null, true);

        $session = $this->sessions->getOrCreateForDate($data['business_date'] ?? null, $request->user());

        return $this->succeed($request, 'Aaj ka rent skip kar diya.', $session, $data['business_date'] ?? null);
    }

    /** Pay rent to the landlord. */
    public function payForm(Request $request): View
    {
        $date = $this->requestedDate($request);
        $obligation = $this->rent->ensureObligation($date->year, $date->month);
        $balances = $this->cash->getBalances();

        return view('rent.pay', [
            'obligation' => $obligation,
            'reserve' => $balances['rent_reserve'],
            'cashInHand' => $balances['canteen_cash'],
            'entryDate' => $this->entryDate($request),
            'topDate' => $date,
        ]);
    }

    public function storePay(PayRentRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $payment = $this->rent->payRent($data, $request->user());

        return $this->succeed(
            $request,
            'Rent de diya: '.Money::format($payment->amount),
            $payment->daily_session_id,
            $data['business_date'] ?? null,
        );
    }
}
