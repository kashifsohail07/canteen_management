<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\ExpenseCategory;
use App\Services\CashLedgerService;
use App\Services\ExpenseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    public function create(Request $request, CashLedgerService $cash): View
    {
        return view('expenses.create', [
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name')->get(),
            'entryDate' => $this->entryDate($request),
            'topDate' => $this->requestedDate($request),
            'cashInHand' => $cash->getBalances()['canteen_cash'],
        ]);
    }

    public function store(StoreExpenseRequest $request, ExpenseService $expenses): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['new_category'])) {
            $name = trim($data['new_category']);
            $category = ExpenseCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? ExpenseCategory::create(['name' => $name, 'is_active' => true]);
            $category->is_active || $category->update(['is_active' => true]);
            $data['expense_category_id'] = $category->id;
        }

        $expense = $expenses->record($data, $request->user());

        return $this->succeed(
            $request,
            'Kharcha save: '.Money::format($expense->amount).($expense->category ? ' ('.$expense->category->name.')' : ''),
            $expense->daily_session_id,
            $data['business_date'] ?? null,
        );
    }
}
