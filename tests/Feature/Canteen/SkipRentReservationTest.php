<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\RentReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class SkipRentReservationTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_skipping_rent_does_not_change_any_balance(): void
    {
        $this->fundCanteenCash(9000);

        $this->actingAs($this->staff)->post('/rent/skip', ['notes' => 'Sales were low today'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertMoney(9000, $this->canteenCash());
        $this->assertMoney(0, $this->rentReserve());
        $this->assertMoney(0, $this->ownerRecoverable());
        $this->assertSame(0, RentReservation::count());
    }

    public function test_skip_leaves_a_note_in_the_activity_feed(): void
    {
        $this->actingAs($this->staff)->post('/rent/skip', ['notes' => 'No reserve today'])->assertRedirect();

        $activity = ActivityLog::where('activity_type', 'rent_skip')->sole();
        $this->assertSame('No reserve today', $activity->summary);
        $this->assertNull($activity->amount);
    }

    public function test_skip_works_with_json_and_with_no_cash_at_all(): void
    {
        $this->actingAs($this->staff)->postJson('/rent/skip')->assertOk()->assertJsonPath('ok', true);

        $this->assertMoney(0, $this->canteenCash());
        $this->assertMoney(0, $this->rentReserve());
    }

    public function test_reserve_can_still_be_made_after_skipping_earlier(): void
    {
        $this->fundCanteenCash(6000);
        $this->actingAs($this->staff)->post('/rent/skip')->assertRedirect();
        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '2000'])->assertRedirect();

        $this->assertMoney(4000, $this->canteenCash());
        $this->assertMoney(2000, $this->rentReserve());
    }
}
