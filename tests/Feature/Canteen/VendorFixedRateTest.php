<?php

namespace Tests\Feature\Canteen;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class VendorFixedRateTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_samosa_vendor_defaults_to_twenty_rupee_rate_in_today_api(): void
    {
        $vendor = $this->makeVendor('Samosay Wala', 0);
        $vendor->update([
            'default_item_name' => 'Samosay',
            'default_unit' => 'pcs',
            'default_rate' => '20.00',
        ]);

        $this->actingAs($this->staff)
            ->getJson(route('purchases.today-deliveries', ['vendor_id' => $vendor->id]))
            ->assertOk()
            ->assertJson([
                'last_rate' => '20.00',
                'last_item_name' => 'Samosay',
                'last_unit' => 'pcs',
                'rate_fixed' => true,
                'default_rate' => '20.00',
            ]);
    }

    public function test_vendor_search_includes_fixed_rate(): void
    {
        $vendor = $this->makeVendor('Samosa King', 0);
        $vendor->update([
            'default_item_name' => 'Samosay',
            'default_unit' => 'pcs',
            'default_rate' => '20.00',
        ]);

        $this->actingAs($this->staff)
            ->getJson(route('vendors.search', ['q' => 'Samosa']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $vendor->id,
                'default_rate' => '20.00',
                'rate_fixed' => true,
                'default_item_name' => 'Samosay',
            ]);
    }

    public function test_admin_can_set_fixed_rate_on_vendor_edit(): void
    {
        $vendor = $this->makeVendor('Roll Wala', 0);

        $this->actingAs($this->admin)->put(route('vendors.update', $vendor), [
            'name' => 'Roll Wala',
            'uses_quantity' => '1',
            'default_item_name' => 'Roll',
            'default_rate' => '20',
            'is_active' => '1',
        ])->assertRedirect(route('vendors.show', $vendor));

        $vendor->refresh();
        $this->assertTrue($vendor->hasFixedRate());
        $this->assertMoney(20, $vendor->default_rate);
        $this->assertSame('Roll', $vendor->default_item_name);
    }

    public function test_create_quantity_vendor_requires_per_pcs_price(): void
    {
        $this->actingAs($this->admin)->post(route('vendors.store'), [
            'name' => 'Samosay New',
            'uses_quantity' => '1',
            'default_item_name' => 'Samosay',
            'opening_balance' => '0',
            'balance_type' => 'owed',
        ])->assertSessionHasErrors('default_rate');
    }

    public function test_create_quantity_vendor_saves_fixed_rate(): void
    {
        $this->actingAs($this->admin)->post(route('vendors.store'), [
            'name' => 'Samosay New',
            'uses_quantity' => '1',
            'default_item_name' => 'Samosay',
            'default_rate' => '20',
            'opening_balance' => '0',
            'balance_type' => 'owed',
        ])->assertRedirect();

        $vendor = \App\Models\Vendor::where('name', 'Samosay New')->first();
        $this->assertNotNull($vendor);
        $this->assertTrue($vendor->hasFixedRate());
        $this->assertMoney(20, $vendor->default_rate);
        $this->assertSame('pcs', $vendor->default_unit);
    }

    public function test_create_without_quantity_checkbox_has_no_fixed_rate(): void
    {
        $this->actingAs($this->admin)->post(route('vendors.store'), [
            'name' => 'Pepsi Only',
            'opening_balance' => '1000',
            'balance_type' => 'owed',
        ])->assertRedirect();

        $vendor = \App\Models\Vendor::where('name', 'Pepsi Only')->first();
        $this->assertNotNull($vendor);
        $this->assertFalse($vendor->hasFixedRate());
        $this->assertNull($vendor->default_rate);
    }
}
