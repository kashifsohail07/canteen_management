<?php

namespace Tests\Feature\Canteen;

use App\Models\VendorLedgerEntry;
use App\Models\VendorPayment;
use App\Services\PurchaseService;
use App\Services\VendorLedgerService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

/**
 * NOTE ON CONCURRENCY
 * -------------------
 * True parallel concurrency (two requests hitting the DB at the same instant) cannot be reproduced reliably in a
 * single-process PHPUnit run, and SQLite (the test DB) serialises writers and ignores `SELECT ... FOR UPDATE`.
 * The app protects balances with row locks (`lockForUpdate()` on the vendor and cash-account rows, always taken in
 * the same order: vendors -> canteen_cash -> rent_reserve -> owner_recoverable) inside DB transactions. On MySQL/InnoDB
 * that makes concurrent writers queue up on the row. These tests therefore verify the *pattern*: each update re-reads
 * the locked row inside its own transaction, so two sequential locked updates always compose correctly (no lost update).
 * Genuine parallel behaviour is DB-engine dependent and should be load-tested against MySQL if needed.
 */
class ConcurrentBalanceUpdateTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_two_sequential_locked_payments_total_correctly(): void
    {
        $this->fundCanteenCash(20000);
        $vendor = $this->makeVendor('Samosay Supplier', 14000);
        $service = app(PurchaseService::class);

        DB::transaction(function () use ($service, $vendor) {
            $service->payVendor(['vendor_id' => $vendor->id, 'amount' => '3000', 'payment_source' => 'canteen_cash'], $this->staff);
        });
        DB::transaction(function () use ($service, $vendor) {
            $service->payVendor(['vendor_id' => $vendor->id, 'amount' => '2500', 'payment_source' => 'canteen_cash'], $this->staff);
        });

        $this->assertMoney(8500, $this->vendorBalance($vendor));
        $this->assertMoney(14500, $this->canteenCash());

        // each payment saw the balance left by the previous one
        $payments = VendorPayment::orderBy('id')->get();
        $this->assertMoney(14000, $payments[0]->previous_balance);
        $this->assertMoney(11000, $payments[0]->new_balance);
        $this->assertMoney(11000, $payments[1]->previous_balance);
        $this->assertMoney(8500, $payments[1]->new_balance);
    }

    public function test_two_locked_ledger_updates_use_fresh_locked_rows(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);
        $ledger = app(VendorLedgerService::class);

        // "request A" and "request B" both start from a stale copy of the vendor (balance 1000)
        $staleA = $vendor->fresh();
        $staleB = $vendor->fresh();

        DB::transaction(function () use ($ledger, $staleA) {
            $locked = $ledger->lockVendor($staleA); // re-reads the row under lock
            $ledger->recordPurchaseEffect($locked, 500, ['user' => $this->staff]);
        });
        DB::transaction(function () use ($ledger, $staleB) {
            $locked = $ledger->lockVendor($staleB);
            $this->assertMoney(1500, $locked->current_balance, 'second update must see the first update.');
            $ledger->recordPaymentEffect($locked, 200, ['user' => $this->staff]);
        });

        $this->assertMoney(1300, $this->vendorBalance($vendor));

        // ledger is a consistent running balance
        $running = Money::zero();
        foreach (VendorLedgerEntry::where('vendor_id', $vendor->id)->orderBy('id')->get() as $entry) {
            $running = Money::sub(Money::add($running, $entry->debit), $entry->credit);
            $this->assertMoney($running, $entry->balance_after);
        }
        $this->assertMoney(1300, $running);
    }

    public function test_interleaved_purchases_and_payments_keep_ledger_and_cash_consistent(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Pepsi', 0);
        $service = app(PurchaseService::class);

        $service->store(['vendor_id' => $vendor->id, 'bill_amount' => 4000, 'paid_amount' => 1000, 'payment_source' => 'canteen_cash'], $this->staff);
        $service->payVendor(['vendor_id' => $vendor->id, 'amount' => 500, 'payment_source' => 'canteen_cash'], $this->staff);
        $service->store(['vendor_id' => $vendor->id, 'bill_amount' => 1500], $this->staff);
        $service->payVendor(['vendor_id' => $vendor->id, 'amount' => 2000, 'payment_source' => 'personal_pocket'], $this->staff);

        // 4000 - 1000 - 500 + 1500 - 2000
        $this->assertMoney(2000, $this->vendorBalance($vendor));
        $this->assertMoney(8500, $this->canteenCash());
        $this->assertMoney(2000, $this->ownerRecoverable());

        $last = VendorLedgerEntry::where('vendor_id', $vendor->id)->orderByDesc('id')->first();
        $this->assertMoney(2000, $last->balance_after);
    }

    public function test_failed_transaction_rolls_back_without_corrupting_balances(): void
    {
        $this->fundCanteenCash(100);
        $vendor = $this->makeVendor('Pepsi', 1000);
        $service = app(PurchaseService::class);

        $service->payVendor(['vendor_id' => $vendor->id, 'amount' => '60', 'payment_source' => 'canteen_cash'], $this->staff);

        try {
            // second payment exceeds remaining cash (40) -> must roll back entirely
            $service->payVendor(['vendor_id' => $vendor->id, 'amount' => '60', 'payment_source' => 'canteen_cash'], $this->staff);
            $this->fail('Expected insufficient cash validation error.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $this->assertMoney(940, $this->vendorBalance($vendor));
        $this->assertMoney(40, $this->canteenCash());
        $this->assertSame(1, VendorPayment::count());
    }
}
