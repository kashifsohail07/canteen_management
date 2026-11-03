<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBusinessDate;
use App\Http\Controllers\Concerns\RespondsToForms;
use App\Http\Requests\AdjustVendorBalanceRequest;
use App\Http\Requests\SetOpeningBalanceRequest;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreCustomerUdhaarRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerLedgerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use ResolvesBusinessDate, RespondsToForms;

    public function __construct(private CustomerLedgerService $ledger) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $q = trim((string) $request->query('q', ''));
        $filter = $request->query('filter', 'all');

        $query = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);
                $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            })
            ->when($filter === 'owed', fn ($query) => $query->where('current_balance', '>', 0))
            ->when($filter === 'advance', fn ($query) => $query->where('current_balance', '<', 0))
            ->orderBy('name');

        $owedCustomers = Customer::where('is_active', true)->where('current_balance', '>', 0)->get(['current_balance']);
        $totalOwed = '0.00';
        $owedCustomers->each(function ($c) use (&$totalOwed) {
            $totalOwed = Money::add($totalOwed, $c->current_balance);
        });

        return view('customers.index', [
            'customers' => $query->paginate(40)->withQueryString(),
            'q' => $q,
            'filter' => $filter,
            'totalOwed' => $totalOwed,
            'owedCount' => $owedCustomers->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->ledger->createCustomer([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'notes' => $request->validated('notes'),
            'opening_balance' => $request->signedOpeningBalance(),
        ], $request->user());

        return redirect()->route('customers.show', $customer)->with('success', "Customer \"{$customer->name}\" ban gaya.");
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        $entries = $customer->ledgerEntries()
            ->with('creator:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('customers.show', [
            'customer' => $customer,
            'entries' => $entries,
            'isAdmin' => (bool) request()->user()?->isAdmin(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'isAdmin' => (bool) request()->user()?->isAdmin(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $customer->update([
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer update ho gaya.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $name = $customer->name;
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', "Customer \"{$name}\" delete ho gaya.");
    }

    public function adjustBalance(AdjustVendorBalanceRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('adjustBalance', $customer);

        $this->ledger->adjustBalance($customer, $request->validated('new_balance'), $request->validated('reason'), $request->user());

        return redirect()->route('customers.show', $customer)->with('success', 'Balance adjust ho gaya.');
    }

    public function setOpeningBalance(SetOpeningBalanceRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('manageOpeningBalance', $customer);

        $this->ledger->setOpeningBalance($customer, $request->validated('opening_balance'), $request->validated('reason'), $request->user());

        return redirect()->route('customers.show', $customer)->with('success', 'Opening balance badal gaya.');
    }

    public function udhaarForm(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $customer = null;
        if ($request->filled('customer')) {
            $customer = Customer::find($request->query('customer'));
        }

        return view('customers.udhaar', [
            'customer' => $customer,
            'entryDate' => $this->entryDate($request),
            'topDate' => $this->requestedDate($request),
        ]);
    }

    public function storeUdhaar(StoreCustomerUdhaarRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $entry = $this->ledger->recordUdhaar($data, $request->user());
        $customer = $entry->customer;
        $message = 'Udhaar save: '.$customer->name.' · '.Money::format($entry->debit);

        if ($request->expectsJson()) {
            return $this->succeed($request, $message, $entry->daily_session_id, $data['business_date'] ?? null);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', $message);
    }

    public function paymentForm(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $customer = null;
        if ($request->filled('customer')) {
            $customer = Customer::find($request->query('customer'));
        }

        return view('customers.payment', [
            'customer' => $customer,
            'entryDate' => $this->entryDate($request),
            'topDate' => $this->requestedDate($request),
        ]);
    }

    public function storePayment(StoreCustomerPaymentRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $entry = $this->ledger->recordPayment($data, $request->user());
        $customer = $entry->customer;
        $message = 'Payment mili: '.$customer->name.' · '.Money::format($entry->credit);

        if ($request->expectsJson()) {
            return $this->succeed($request, $message, $entry->daily_session_id, $data['business_date'] ?? null);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', $message);
    }
}
