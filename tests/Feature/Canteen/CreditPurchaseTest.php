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
        $this->assertSame('Pepsi', $activity->title);
        $this->assertSame($vendor->id, $activity->vendor_id);
        $this->assertMoney(800, $activity->amount);
        $this->assertStringContainsString('Previous', $activity->summary);
        $this->assertStringContainsString('Bill', $activity->summary);
        $this->assertStringContainsString('Balance', $activity->summary);
        $this->assertSame('purchase_v2', $activity->meta['layout'] ?? null);
    }

    public function test_qty_vendor_purchase_activity_shows_qty_then_details_with_balance(): void
    {
        $vendor = $this->makeVendor('Samosay Supplier', 14000);
        $vendor->update([
            'default_item_name' => 'Samosay',
            'default_unit' => 'pcs',
            'default_rate' => '20.00',
        ]);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'entry_mode' => 'itemized',
            'items' => [
                ['item_name' => 'Samosay', 'quantity' => '200', 'unit' => 'pcs', 'rate' => '20'],
            ],
        ])->assertRedirect();

        $activity = ActivityLog::sole();
        $this->assertSame('Samosay Supplier', $activity->title);
        $this->assertStringStartsWith("Qty 200 pcs\nBill Rs. 4,000\nPrevious", $activity->summary);
        $this->assertStringContainsString('On credit', $activity->summary);
        $this->assertStringContainsString('Balance Rs. 18,000', $activity->summary);
        $this->assertSame('200 pcs', $activity->meta['qty_label'] ?? null);
        $this->assertSame('Rs. 18,000', $activity->meta['balance'] ?? null);
    }

    public function test_itemized_purchase_activity_sums_total_qty(): void
    {
        $vendor = $this->makeVendor('Samosay Supplier', 0);
        $vendor->update([
            'default_item_name' => 'Samosay',
            'default_unit' => 'pcs',
            'default_rate' => '20.00',
        ]);

        $this->actingAs($this->staff)->post('/purchases', [
            'vendor_id' => $vendor->id,
            'entry_mode' => 'itemized',
            'items' => [
                ['item_name' => 'Samosay', 'quantity' => '100', 'unit' => 'pcs', 'rate' => '20'],
                ['item_name' => 'Samosay', 'quantity' => '50', 'unit' => 'pcs', 'rate' => '20'],
            ],
        ])->assertRedirect();

        $activity = ActivityLog::sole();
        $this->assertStringStartsWith('Qty 150 pcs', $activity->summary);
        $this->assertSame('150 pcs', $activity->meta['qty_label'] ?? null);
    }

    public function test_purchase_json_response_reports_new_summary(): void
    {
        $vendor = $this->makeVendor('Pepsi', 200);

        $this->actingAs($this->staff)->postJson('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '800'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
