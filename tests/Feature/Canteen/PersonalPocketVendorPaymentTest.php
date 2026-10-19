<?php

namespace Tests\Feature\Canteen;

use App\Models\OwnerTransaction;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class PersonalPocketVendorPaymentTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_personal_payment_leaves_cash_unchanged_and_increases_owner_recoverable(): void
    {
        $this->fundCanteenCash(3000);
        $vendor = $this->makeVendor('Fruit Gala', 7300);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id,
            'amount' => '2300',
            'payment_source' => 'personal_pocket',
        ])->assertRedirect(route('dashboard'));

        $this->assertMoney(5000, $this->vendorBalance($vendor));
        $this->assertMoney(3000, $this->canteenCash());
        $this->assertMoney(2300, $this->ownerRecoverable());

        $payment = VendorPayment::sole();
        $this->assertSame('personal_pocket', $payment->payment_source->value ?? $payment->payment_source);

        $tx = OwnerTransaction::sole();
        $this->assertSame('vendor_payment', $tx->type);
        $this->assertSame('increase_recoverable', $tx->direction);
        $this->assertSame($payment->id, $tx->vendor_payment_id);
        $this->assertMoney(2300, $tx->balance_after);
    }

    public function test_personal_payment_works_even_when_canteen_cash_is_empty(): void
    {
        $vendor = $this->makeVendor('Pepsi', 1000);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id,
            'amount' => '1000',
            'payment_source' => 'personal_pocket',
        ])->assertRedirect();

        $this->assertMoney(0, $this->vendorBalance($vendor));
        $this->assertMoney(0, $this->canteenCash());
        $this->assertMoney(1000, $this->ownerRecoverable());
    }

    public function test_personal_part_payment_on_a_purchase_counts_for_the_owner(): void
    {
        $this->fundCanteenCash(500);
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '1500',
            'paid_amount' => '600',
            'payment_source' => 'personal_pocket',
        ])->assertRedirect();

        $this->assertMoney(900, $this->vendorBalance($vendor));
        $this->assertMoney(500, $this->canteenCash());
        $this->assertMoney(600, $this->ownerRecoverable());
    }

    public function test_owner_repayment_reduces_recoverable_and_cash(): void
    {
        $this->fundCanteenCash(2000);
        $vendor = $this->makeVendor('Pepsi', 1000);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id, 'amount' => '1000', 'payment_source' => 'personal_pocket',
        ])->assertRedirect();

        $this->actingAs($this->staff)->post('/owner/repayment', ['amount' => '400'])->assertRedirect();

        $this->assertMoney(600, $this->ownerRecoverable());
        $this->assertMoney(1600, $this->canteenCash());

        // cannot repay more than owed to the owner
        $this->actingAs($this->staff)->post('/owner/repayment', ['amount' => '5000'])->assertSessionHasErrors('amount');
        $this->assertMoney(600, $this->ownerRecoverable());
    }
}
