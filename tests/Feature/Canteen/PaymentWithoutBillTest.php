<?php

namespace Tests\Feature\Canteen;

use App\Models\Purchase;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class PaymentWithoutBillTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_paying_a_vendor_without_a_bill_decreases_balance(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Samosay Supplier', 14000);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id,
            'amount' => '4000',
            'payment_source' => 'canteen_cash',
        ])->assertRedirect(route('dashboard'));

        $this->assertMoney(10000, $this->vendorBalance($vendor));
        $this->assertMoney(6000, $this->canteenCash());
        $this->assertSame(0, Purchase::count());

        $payment = VendorPayment::sole();
        $this->assertMoney(14000, $payment->previous_balance);
        $this->assertMoney(10000, $payment->new_balance);

        $entry = VendorLedgerEntry::where('entry_type', 'payment')->sole();
        $this->assertMoney(4000, $entry->credit);
        $this->assertMoney(10000, $entry->balance_after);
    }

    public function test_purchase_endpoint_rejects_a_payment_only_request(): void
    {
        $vendor = $this->makeVendor('Samosay Supplier', 14000);

        // bill must be > 0: payment-only entries go through /payments
        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '0',
            'paid_amount' => '1000',
            'payment_source' => 'canteen_cash',
        ])->assertSessionHasErrors('bill_amount');

        $this->assertMoney(14000, $this->vendorBalance($vendor));
    }

    public function test_payment_validation(): void
    {
        $vendor = $this->makeVendor('Pepsi', 100);

        $this->actingAs($this->staff)->post('/payments', ['vendor_id' => $vendor->id, 'amount' => '0', 'payment_source' => 'canteen_cash'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post('/payments', ['vendor_id' => $vendor->id, 'amount' => '50'])
            ->assertSessionHasErrors('payment_source');
        $this->actingAs($this->staff)->post('/payments', ['amount' => '50', 'payment_source' => 'canteen_cash'])
            ->assertSessionHasErrors('vendor_id');

        $this->assertSame(0, VendorPayment::count());
    }

    public function test_payment_cannot_exceed_cash_in_hand(): void
    {
        $this->fundCanteenCash(100);
        $vendor = $this->makeVendor('Pepsi', 5000);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id,
            'amount' => '500',
            'payment_source' => 'canteen_cash',
        ])->assertSessionHasErrors('amount');

        $this->assertMoney(5000, $this->vendorBalance($vendor));
        $this->assertMoney(100, $this->canteenCash());
    }
}
