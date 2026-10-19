<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\OwnerTransactionRequest;
use App\Models\OwnerTransaction;
use App\Services\CashLedgerService;
use App\Services\OwnerLedgerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    public function __construct(
        private OwnerLedgerService $owner,
        private CashLedgerService $cash,
    ) {}

    /** Owner ledger. */
    public function index(): View
    {
        $balances = $this->cash->getBalances();

        return view('owner.index', [
            'recoverable' => $balances['owner_recoverable'],
            'cashInHand' => $balances['canteen_cash'],
            'transactions' => OwnerTransaction::with('creator:id,name')
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function deposit(Request $request): View
    {
        return $this->form($request, 'deposit');
    }

    public function repayment(Request $request): View
    {
        return $this->form($request, 'repayment');
    }

    public function storeDeposit(OwnerTransactionRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $tx = $this->owner->deposit($data, $request->user());

        return $this->succeed(
            $request,
            'Owner deposit save: '.Money::format($tx->amount).'. Owner ka baqaya '.Money::format($tx->balance_after),
            $tx->daily_session_id,
            $data['business_date'] ?? null,
        );
    }

    public function storeRepayment(OwnerTransactionRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $tx = $this->owner->repayment($data, $request->user());

        return $this->succeed(
            $request,
            'Owner ko wapis kiya: '.Money::format($tx->amount).'. Baqaya '.Money::format($tx->balance_after),
            $tx->daily_session_id,
            $data['business_date'] ?? null,
        );
    }

    private function form(Request $request, string $type): View
    {
        $balances = $this->cash->getBalances();

        return view('owner.form', [
            'type' => $type,
            'recoverable' => $balances['owner_recoverable'],
            'cashInHand' => $balances['canteen_cash'],
            'entryDate' => $this->entryDate($request),
            'topDate' => $this->requestedDate($request),
        ]);
    }
}
