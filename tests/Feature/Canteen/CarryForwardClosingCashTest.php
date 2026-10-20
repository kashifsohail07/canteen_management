<?php

namespace Tests\Feature\Canteen;

use App\Enums\DailySessionStatus;
use App\Models\DailySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class CarryForwardClosingCashTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
        $this->travelTo(now()->setTime(10, 0));
    }

    public function test_next_day_opening_cash_equals_the_counted_cash(): void
    {
        $this->fundCanteenCash(1000);
        $segment = $this->segment();
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '4000'])->assertRedirect();
        $today = now()->toDateString();

        // expected 5,000 but only 4,900 counted: difference needs a reason
        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('4900', $today))
            ->assertSessionHasErrors('difference_reason');

        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('4900', $today, [
            'difference_reason' => 'Rs. 100 given as change to a teacher',
        ]))->assertRedirect(route('closing.show', ['date' => $today]));

        $closed = DailySession::whereDate('business_date', $today)->sole();
        $this->assertSame(DailySessionStatus::Closed, $closed->status);
        $this->assertMoney(5000, $closed->expected_closing_cash);
        $this->assertMoney(4900, $closed->actual_counted_cash);
        $this->assertMoney(-100, $closed->cash_difference);
        $this->assertMoney(4900, $this->canteenCash());

        // next business day
        $this->travelTo(now()->addDay()->setTime(10, 0));
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '600'])->assertRedirect();

        $next = DailySession::whereDate('business_date', now()->toDateString())->sole();
        $this->assertTrue($next->isOpen());
        $this->assertMoney(4900, $next->opening_canteen_cash);
        $this->assertMoney(5500, $this->canteenCash());
    }

    public function test_exact_count_closes_without_a_reason(): void
    {
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '700'])->assertRedirect();

        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('700'))->assertRedirect();

        $session = DailySession::sole();
        $this->assertMoney(0, $session->cash_difference);
        $this->assertNull($session->difference_reason);
    }

    public function test_closed_day_rejects_new_entries(): void
    {
        $segment = $this->segment();
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '700'])->assertRedirect();
        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('700'))->assertRedirect();

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '50'])
            ->assertSessionHasErrors('business_date');

        $this->assertMoney(700, $this->canteenCash());
    }

    public function test_admin_can_reopen_a_closed_day_and_the_adjustment_is_reversed(): void
    {
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '1000'])->assertRedirect();
        $today = now()->toDateString();
        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('950', $today, [
            'difference_reason' => 'Short',
        ]))->assertRedirect();
        $this->assertMoney(950, $this->canteenCash());

        $this->actingAs($this->admin)->post('/closing/reopen', ['business_date' => $today, 'reason' => 'Forgot an entry'])
            ->assertRedirect();

        $this->assertTrue(DailySession::sole()->isOpen());
        $this->assertMoney(1000, $this->canteenCash());
    }
}
