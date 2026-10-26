<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerLedgerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __invoke(Request $request, CustomerLedgerService $customers): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $results = $customers->searchCustomers($request->query('q'), (int) $request->query('limit', 10));

        return response()->json($results->map(fn (Customer $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'balance' => Money::of($c->current_balance),
            'balance_formatted' => Money::format($c->current_balance),
            'phone' => $c->phone,
        ])->values());
    }
}
