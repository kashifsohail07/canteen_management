<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustVendorBalanceRequest;
use App\Http\Requests\SetOpeningBalanceRequest;
use App\Http\Requests\StoreVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Purchase;
use App\Models\Vendor;
use App\Services\VendorLedgerService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private VendorLedgerService $ledger) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vendor::class);

        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all');

        $query = Vendor::query()
            ->when($q !== '', function ($query) use ($q) {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
                $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            })
            ->when($filter === 'owed', fn ($query) => $query->where('current_balance', '>', 0))
            ->when($filter === 'advance', fn ($query) => $query->where('current_balance', '<', 0))
            ->orderBy('name');

        $totalOwed = '0.00';
        Vendor::where('is_active', true)->where('current_balance', '>', 0)->get(['current_balance'])
            ->each(function ($v) use (&$totalOwed) {
                $totalOwed = Money::add($totalOwed, $v->current_balance);
            });

        return view('vendors.index', [
            'vendors' => $query->paginate(40)->withQueryString(),
            'q' => $q,
            'filter' => $filter,
            'totalOwed' => $totalOwed,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vendor::class);

        return view('vendors.create');
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $vendor = $this->ledger->createVendor([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'notes' => $request->validated('notes'),
            'opening_balance' => $request->signedOpeningBalance(),
            'default_item_name' => $request->validated('default_item_name'),
            'default_unit' => $request->validated('default_unit'),
            'default_rate' => $request->validated('default_rate'),
        ], $request->user());

        return redirect()->route('vendors.show', $vendor)->with('success', "Vendor \"{$vendor->name}\" ban gaya.");
    }

    public function show(Vendor $vendor): View
    {
        Gate::authorize('view', $vendor);

        $entries = $vendor->ledgerEntries()
            ->with('creator:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(25);

        // purchases referenced on this page (for bill photos)
        $purchaseIds = $entries->getCollection()->where('reference_type', 'purchase')->pluck('reference_id')->all();
        $purchases = Purchase::whereIn('id', $purchaseIds)->get()->keyBy('id');

        return view('vendors.show', [
            'vendor' => $vendor,
            'entries' => $entries,
            'purchases' => $purchases,
            'isAdmin' => (bool) request()->user()?->isAdmin(),
        ]);
    }

    public function edit(Vendor $vendor): View
    {
        Gate::authorize('update', $vendor);

        return view('vendors.edit', [
            'vendor' => $vendor,
            'isAdmin' => (bool) request()->user()?->isAdmin(),
        ]);
    }

    /** Soft-delete: history (ledger / bills) safe; vendor list se hat jata hai. */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        Gate::authorize('delete', $vendor);

        $name = $vendor->name;
        $vendor->delete();

        return redirect()
            ->route('vendors.index')
            ->with('success', "Vendor \"{$name}\" delete ho gaya.");
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validated();
        $vendor->update([
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'default_item_name' => $data['default_item_name'] ?? null,
            'default_unit' => $data['default_rate'] ? ($data['default_unit'] ?? 'pcs') : null,
            'default_rate' => $data['default_rate'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('vendors.show', $vendor)->with('success', 'Vendor update ho gaya.');
    }

    /** Admin: force vendor balance to a new value (reason required). */
    public function adjustBalance(AdjustVendorBalanceRequest $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('adjustBalance', $vendor);

        $this->ledger->adjustBalance($vendor, $request->validated('new_balance'), $request->validated('reason'), $request->user());

        return redirect()->route('vendors.show', $vendor)->with('success', 'Balance adjust ho gaya.');
    }

    /** Admin: change the locked opening balance (reason required). */
    public function setOpeningBalance(SetOpeningBalanceRequest $request, Vendor $vendor): RedirectResponse
    {
        Gate::authorize('manageOpeningBalance', $vendor);

        $this->ledger->setOpeningBalance($vendor, $request->validated('opening_balance'), $request->validated('reason'), $request->user());

        return redirect()->route('vendors.show', $vendor)->with('success', 'Opening balance badal gaya.');
    }
}
