<?php

namespace Tests\Feature\Canteen;

use App\Models\Purchase;
use App\Services\DailySessionService;
use App\Services\DashboardService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class AdvancePaymentTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_paying_more_than_owed_makes_the_balance_negative_advance(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Pepsi', 1000);

        $this->actingAs($this->staff)->post('/payments', [
            'vendor_id' => $vendor->id,
            'amount' => '3000',
            'payment_source' => 'canteen_cash',
        ])->assertRedirect(route('dashboard'));

        $this->assertMoney(-2000, $this->vendorBalance($vendor));
        $this->assertMoney(7000, $this->canteenCash());
    }

    public function test_advance_for_a_vendor_with_zero_balance(): void
    {
        $this->fundCanteenCash(1000);
        $vendor = $this->makeVendor('New Vendor', 0);

        app(PurchaseService::class)->payVendor([
            'vendor_id' => $vendor->id,
            'amount' => '400',
            'payment_source' => 'canteen_cash',
        ], $this->staff);

        $this->assertMoney(-400, $this->vendorBalance($vendor));
    }

    public function test_later_purchase_is_set_off_against_the_advance(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Pepsi', 0);
        $service = app(PurchaseService::class);

        $service->payVendor(['vendor_id' => $vendor->id, 'amount' => '2000', 'payment_source' => 'canteen_cash'], $this->staff);
        $this->assertMoney(-2000, $this->vendorBalance($vendor));

        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '500'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertMoney(-1500, $this->vendorBalance($vendor));
        $purchase = Purchase::sole();
        $this->assertMoney(-2000, $purchase->previous_balance);
        $this->assertMoney(-1500, $purchase->new_balance);
        $this->assertMoney(8000, $this->canteenCash());
    }

    public function test_purchase_with_overpayment_creates_an_advance(): void
    {
        $this->fundCanteenCash(5000);
        $vendor = $this->makeVendor('Pepsi', 300);

        // bill 200 + owed 300 = 500, but 800 paid -> advance of 300
        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '200',
            'paid_amount' => '800',
            'payment_source' => 'canteen_cash',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertMoney(-300, $this->vendorBalance($vendor));
        $this->assertMoney(4200, $this->canteenCash());
        $this->assertMoney(-300, Purchase::sole()->new_balance);
    }

    public function test_vendor_list_filters_advance_vendors(): void
    {
        $this->makeVendor('Overpaid Vendor', -750);
        $this->makeVendor('Owed Vendor', 300);

        $this->actingAs($this->staff)->get('/vendors?filter=advance')
            ->assertOk()
            ->assertSee('Overpaid Vendor')
            ->assertDontSee('Owed Vendor');
    }

    public function test_advance_balance_is_not_counted_as_money_owed(): void
    {
        $this->makeVendor('Overpaid Vendor', -750);
        $this->makeVendor('Owed Vendor', 300);

        $summary = app(DashboardService::class)->summary(
            app(DailySessionService::class)->getOrCreateForDate(now(), $this->staff)
        );

        $this->assertMoney(300, $summary['vendors_owed']);
    }
}
