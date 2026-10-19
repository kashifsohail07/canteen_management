<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class HomepageActivityTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_dashboard_activity_contains_sale_and_purchase(): void
    {
        $vendor = $this->makeVendor('Samosay Supplier', 0);

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '3000'])->assertRedirect();
        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '1800'])->assertRedirect();

        $this->assertSame(2, ActivityLog::count());

        $this->actingAs($this->staff)->get('/')
            ->assertOk()
            ->assertSee('Purchase from Samosay Supplier')
            ->assertSee('First Break');

        $this->actingAs($this->staff)->getJson('/dashboard/activity')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('has_more', false);
    }

    public function test_activity_feed_shows_all_of_todays_entries_newest_first(): void
    {
        $vendor = $this->makeVendor('Pepsi', 1000);
        $this->fundCanteenCash(5000);
        $segment = $this->segment();

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '100'])->assertRedirect();
        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '200'])->assertRedirect();
        $this->actingAs($this->staff)->post('/payments', ['vendor_id' => $vendor->id, 'amount' => '300', 'payment_source' => 'canteen_cash'])->assertRedirect();
        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '400'])->assertRedirect();
        $this->actingAs($this->staff)->post('/owner/deposit', ['amount' => '500'])->assertRedirect();

        $types = ActivityLog::orderByDesc('recorded_at')->orderByDesc('id')->pluck('activity_type')->all();
        $this->assertSame(['owner_deposit', 'rent_reserve', 'vendor_payment', 'purchase', 'sale'], $types);

        $this->actingAs($this->staff)->getJson('/dashboard/activity')
            ->assertOk()
            ->assertJsonPath('total', 5);
    }

    public function test_activity_is_per_day_and_older_days_are_not_in_todays_feed(): void
    {
        $segment = $this->segment();
        $this->travelTo(now()->subDay()->setTime(10, 0));
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '100'])->assertRedirect();
        $this->actingAs($this->admin)->post('/closing', ['business_date' => now()->toDateString(), 'actual_counted_cash' => '100'])->assertRedirect();

        $this->travelBack();
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '250'])->assertRedirect();

        $this->actingAs($this->staff)->getJson('/dashboard/activity')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_dashboard_summary_shows_balances(): void
    {
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '4321'])->assertRedirect();

        $this->actingAs($this->staff)->get('/')->assertOk()->assertSee('4,321');
    }
}
