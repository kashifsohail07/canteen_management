<?php

namespace Tests\Feature\Canteen;

use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class VendorOpeningBalanceTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_vendor_created_with_opening_balance_has_matching_balance_and_ledger_entry(): void
    {
        $response = $this->actingAs($this->admin)->post('/vendors', [
            'name' => 'Samosay Supplier',
            'phone' => '0300-1234567',
            'opening_balance' => '14,000',
        ]);

        $vendor = Vendor::where('name', 'Samosay Supplier')->firstOrFail();
        $response->assertRedirect(route('vendors.show', $vendor));

        $this->assertMoney(14000, $vendor->current_balance);
        $this->assertMoney(14000, $vendor->opening_balance);
        $this->assertTrue($vendor->opening_balance_locked);

        $entry = VendorLedgerEntry::where('vendor_id', $vendor->id)->sole();
        $this->assertSame('opening_balance', $entry->entry_type);
        $this->assertMoney(14000, $entry->debit);
        $this->assertMoney(0, $entry->credit);
        $this->assertMoney(14000, $entry->balance_after);
    }

    public function test_vendor_without_opening_balance_starts_at_zero_with_no_ledger_entry(): void
    {
        $this->actingAs($this->staff)->post('/vendors', ['name' => 'Pepsi'])->assertRedirect();

        $vendor = Vendor::where('name', 'Pepsi')->firstOrFail();
        $this->assertMoney(0, $vendor->current_balance);
        $this->assertSame(0, VendorLedgerEntry::where('vendor_id', $vendor->id)->count());
    }

    public function test_negative_opening_balance_means_advance_paid(): void
    {
        $this->actingAs($this->admin)->post('/vendors', [
            'name' => 'Advance Vendor',
            'opening_balance' => '2500',
            'balance_type' => 'advance',
        ])->assertRedirect();

        $vendor = Vendor::where('name', 'Advance Vendor')->firstOrFail();
        $this->assertMoney(-2500, $vendor->current_balance);
        $this->assertMoney(-2500, $vendor->opening_balance);

        $entry = VendorLedgerEntry::where('vendor_id', $vendor->id)->sole();
        $this->assertMoney(0, $entry->debit);
        $this->assertMoney(2500, $entry->credit);
        $this->assertMoney(-2500, $entry->balance_after);
    }

    public function test_service_accepts_negative_opening_balance_directly(): void
    {
        $vendor = $this->makeVendor('Overpaid', '-1000');

        $this->assertMoney(-1000, $vendor->current_balance);
    }

    public function test_duplicate_vendor_name_is_rejected(): void
    {
        $this->makeVendor('Pepsi', 0);

        $this->actingAs($this->admin)->post('/vendors', ['name' => 'pepsi'])->assertSessionHasErrors('name');

        $this->assertSame(1, Vendor::count());
    }

    public function test_admin_can_change_opening_balance_and_current_balance_moves_with_it(): void
    {
        $vendor = $this->makeVendor('Biscuits', 5000);

        $this->actingAs($this->admin)
            ->post("/vendors/{$vendor->id}/opening-balance", ['opening_balance' => '6000', 'reason' => 'Correction after recount'])
            ->assertRedirect(route('vendors.show', $vendor));

        $vendor->refresh();
        $this->assertMoney(6000, $vendor->opening_balance);
        $this->assertMoney(6000, $vendor->current_balance);
    }

    public function test_admin_can_set_negative_opening_balance(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);

        $this->actingAs($this->admin)
            ->post("/vendors/{$vendor->id}/opening-balance", ['opening_balance' => '-500', 'reason' => 'Vendor was overpaid'])
            ->assertRedirect();

        $this->assertMoney(-500, $this->vendorBalance($vendor));
    }
}
