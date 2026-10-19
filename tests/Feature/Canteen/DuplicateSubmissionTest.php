<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\VendorPayment;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class DuplicateSubmissionTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_same_idempotency_key_on_sales_has_no_double_cash_effect(): void
    {
        $payload = ['sale_segment_id' => $this->segment()->id, 'amount' => '1500', 'idempotency_key' => 'sale-key-1'];

        $this->actingAs($this->staff)->post('/sales', $payload)->assertRedirect();
        $this->actingAs($this->staff)->post('/sales', $payload)->assertRedirect();

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, ActivityLog::where('activity_type', 'sale')->count());
        $this->assertMoney(1500, $this->canteenCash());
    }

    public function test_idempotency_key_header_is_honoured_for_purchases(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);
        $payload = ['vendor_id' => $vendor->id, 'bill_amount' => '1000'];

        $this->actingAs($this->staff)->withHeader('X-Idempotency-Key', 'purchase-key-1')->post('/purchases', $payload)->assertRedirect();
        $this->actingAs($this->staff)->withHeader('X-Idempotency-Key', 'purchase-key-1')->post('/purchases', $payload)->assertRedirect();

        $this->assertSame(1, Purchase::count());
        $this->assertMoney(1000, $this->vendorBalance($vendor));
    }

    public function test_same_idempotency_key_on_vendor_payments_pays_once(): void
    {
        $this->fundCanteenCash(5000);
        $vendor = $this->makeVendor('Pepsi', 3000);
        $payload = ['vendor_id' => $vendor->id, 'amount' => '1000', 'payment_source' => 'canteen_cash', 'idempotency_key' => 'pay-key-1'];

        $this->actingAs($this->staff)->post('/payments', $payload)->assertRedirect();
        $this->actingAs($this->staff)->post('/payments', $payload)->assertRedirect();

        $this->assertSame(1, VendorPayment::count());
        $this->assertMoney(2000, $this->vendorBalance($vendor));
        $this->assertMoney(4000, $this->canteenCash());
    }

    public function test_services_return_the_same_record_for_a_repeated_key(): void
    {
        $this->fundCanteenCash(1000);
        $vendor = $this->makeVendor('Pepsi', 0);

        $sales = app(SaleService::class);
        $a = $sales->record(['sale_segment_id' => $this->segment()->id, 'amount' => 200, 'idempotency_key' => 'k-sale'], $this->staff);
        $b = $sales->record(['sale_segment_id' => $this->segment()->id, 'amount' => 200, 'idempotency_key' => 'k-sale'], $this->staff);
        $this->assertSame($a->id, $b->id);

        $purchases = app(PurchaseService::class);
        $p1 = $purchases->store(['vendor_id' => $vendor->id, 'bill_amount' => 300, 'paid_amount' => 100, 'payment_source' => 'canteen_cash', 'idempotency_key' => 'k-purchase'], $this->staff);
        $p2 = $purchases->store(['vendor_id' => $vendor->id, 'bill_amount' => 300, 'paid_amount' => 100, 'payment_source' => 'canteen_cash', 'idempotency_key' => 'k-purchase'], $this->staff);
        $this->assertSame($p1->id, $p2->id);

        // 1000 + 200 (sale) - 100 (paid) = 1100, applied once
        $this->assertMoney(1100, $this->canteenCash());
        $this->assertMoney(200, $this->vendorBalance($vendor));
    }

    public function test_different_keys_are_separate_entries(): void
    {
        $segment = $this->segment();
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '100', 'idempotency_key' => 'a'])->assertRedirect();
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '100', 'idempotency_key' => 'b'])->assertRedirect();

        $this->assertSame(2, Sale::count());
        $this->assertMoney(200, $this->canteenCash());
    }
}
