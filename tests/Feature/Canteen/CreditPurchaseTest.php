<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\VendorLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class CreditPurchaseTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_purchase_without_payment_increases_vendor_balance_and_leaves_cash_unchanged(): void
    {
        $this->fundCanteenCash(10000);
        $vendor = $this->makeVendor('Samosay Supplier', 14000);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '3,500',
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $this->assertMoney(17500, $this->vendorBalance($vendor));
        $this->assertMoney(10000, $this->canteenCash());
        $this->assertMoney(0, $this->ownerRecoverable());

        $purchase = Purchase::sole();
        $this->assertMoney(3500, $purchase->bill_amount);
        $this->assertMoney(0, $purchase->paid_amount);
        $this->assertMoney(14000, $purchase->previous_balance);
        $this->assertMoney(17500, $purchase->new_balance);
        $this->assertNull($purchase->payment_source);

        $entry = VendorLedgerEntry::where('vendor_id', $vendor->id)->where('entry_type', 'purchase')->sole();
        $this->assertMoney(3500, $entry->debit);
        $this->assertMoney(17500, $entry->balance_after);
    }

    public function test_itemized_purchase_totals_line_items(): void
    {
        $vendor = $this->makeVendor('Fruit Gala', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'entry_mode' => 'itemized',
            'items' => [
                ['item_name' => 'Apples', 'quantity' => '2.5', 'unit' => 'kg', 'rate' => '300'],
                ['item_name' => 'Bananas', 'quantity' => '3', 'unit' => 'dozen', 'rate' => '150'],
            ],
        ])->assertRedirect();

        $purchase = Purchase::with('items')->sole();
        $this->assertMoney(1200, $purchase->bill_amount); // 750 + 450
        $this->assertCount(2, $purchase->items);
        $this->assertMoney(1200, $this->vendorBalance($vendor));
    }

    public function test_purchase_requires_a_positive_bill(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '0'])
            ->assertSessionHasErrors('bill_amount');
        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id])
            ->assertSessionHasErrors('bill_amount');

        $this->assertSame(0, Purchase::count());
        $this->assertMoney(0, $this->vendorBalance($vendor));
    }

    public function test_payment_requires_a_source(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'bill_amount' => '1000',
            'paid_amount' => '500',
        ])->assertSessionHasErrors('payment_source');

        $this->assertSame(0, Purchase::count());
    }

    public function test_purchase_creates_a_dashboard_activity_entry(): void
    {
        $vendor = $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '800'])->assertRedirect();

        $activity = ActivityLog::sole();
        $this->assertSame('purchase', $activity->activity_type);
        $this->assertSame($vendor->id, $activity->vendor_id);
        $this->assertMoney(800, $activity->amount);
    }

    public function test_purchase_json_response_reports_new_summary(): void
    {
        $vendor = $this->makeVendor('Pepsi', 200);

        $this->actingAs($this->staff)->postJson('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '800'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
