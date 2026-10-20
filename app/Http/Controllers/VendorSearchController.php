<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\VendorLedgerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSearchController extends Controller
{
    public function __invoke(Request $request, VendorLedgerService $vendors): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $results = $vendors->searchVendors($request->query('q'), (int) $request->query('limit', 10));

        return response()->json($results->map(fn (Vendor $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'balance' => Money::of($v->current_balance),
            'balance_formatted' => Money::format($v->current_balance),
            'phone' => $v->phone,
            'default_item_name' => $v->default_item_name,
            'default_unit' => $v->default_unit,
            'default_rate' => $v->default_rate !== null ? Money::of($v->default_rate) : null,
            'rate_fixed' => $v->hasFixedRate(),
        ])->values());
    }
}
