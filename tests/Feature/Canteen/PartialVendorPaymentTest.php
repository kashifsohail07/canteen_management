<?php

namespace Tests\Feature\Canteen;

use App\Models\Purchase;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class PartialVendorPaymentTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_bill_with_partial_payment_updates_balance_and_cash(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Biscuits', 5000);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '4000',
            'paid_amount' => '1500',
            'payment_source' => 'canteen_cash',
        ])->assertRedirect(route('dashboard'));

        // balance = previous (5000) + bill (4000) - paid (1500)
        $this->assertMoney(7500, $this->vendorBalance($vendor));
        $this->assertMoney(8500, $this->canteenCash());
        $this->assertMoney(0, $this->ownerRecoverable());

        $purchase = Purchase::sole();
        $this->assertMoney(5000, $purchase->previous_balance);
        $this->assertMoney(7500, $purchase->new_balance);
        $this->assertMoney(1500, $purchase->paid_amount);
        $this->assertSame('canteen_cash', $purchase->payment_source);

        $payment = VendorPayment::sole();
        $this->assertSame($purchase->id, $payment->purchase_id);
        $this->assertMoney(1500, $payment->amount);
    }

    public function test_fully_paid_purchase_leaves_vendor_balance_unchanged(): void
    {
        $this->fundCanteenCash(5000);
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '2000',
            'paid_amount' => '2000',
            'payment_source' => 'canteen_cash',
        ])->assertRedirect();

        $this->assertMoney(0, $this->vendorBalance($vendor));
        $this->assertMoney(3000, $this->canteenCash());
    }

    public function test_cash_payment_cannot_exceed_cash_in_hand(): void
    {
        $this->fundCanteenCash(500);
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '2000',
            'paid_amount' => '1000',
            'payment_source' => 'canteen_cash',
        ])->assertSessionHasErrors('amount');

        // whole transaction rolled back
        $this->assertSame(0, Purchase::count());
        $this->assertMoney(0, $this->vendorBalance($vendor));
        $this->assertMoney(500, $this->canteenCash());
    }
}
