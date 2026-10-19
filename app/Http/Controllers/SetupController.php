<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetupVendorsRequest;
use App\Models\Vendor;
use App\Services\VendorLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(private VendorLedgerService $ledger) {}

    /** First-time vendor setup wizard (admin only). */
    public function vendors(): View
    {
        return view('setup.vendors', [
            'existing' => Vendor::orderBy('name')->get(['id', 'name', 'current_balance']),
            'isFirstTime' => ! Vendor::query()->exists(),
        ]);
    }

    public function storeVendors(SetupVendorsRequest $request): RedirectResponse
    {
        $rows = $request->validated('vendors');

        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $i => $row) {
                try {
                    $amount = (string) ($row['opening_balance'] ?? '0');
                    $amount = $amount === '' ? '0' : ltrim($amount, '-');

                    $this->ledger->createVendor([
                        'name' => $row['name'],
                        'phone' => $row['phone'] ?? null,
                        'opening_balance' => ($row['balance_type'] ?? 'owed') === 'advance' ? '-'.$amount : $amount,
                    ], $request->user());
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        "vendors.$i.name" => collect($e->errors())->flatten()->first(),
                    ]);
                }
            }
        });

        $request->session()->forget('setup_skipped');

        return redirect()->route('dashboard')->with('success', count($rows).' vendor(s) add ho gaye. Ab hisab shuru karein!');
    }
}
