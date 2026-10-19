<?php

namespace Tests\Feature\Canteen;

use App\Models\Expense;
use App\Models\RentObligation;
use App\Models\RentReservation;
use App\Services\DailyClosingService;
use App\Services\DailySessionService;
use App\Services\RentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class RentReservationTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_reserving_rent_moves_cash_into_rent_reserve_and_is_not_an_expense(): void
    {
        $this->fundCanteenCash(12000);

        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '5000'])
            ->assertRedirect(route('dashboard'));

        $this->assertMoney(7000, $this->canteenCash());
        $this->assertMoney(5000, $this->rentReserve());

        // internal transfer: not recorded as an expense
        $this->assertSame(0, Expense::count());
        $session = app(DailySessionService::class)->findForDate(now());
        $summary = app(DailyClosingService::class)->summarize($session);
        $this->assertMoney(0, $summary['expenses_total']);
        $this->assertMoney(5000, $summary['rent_reserved']);
        // expected closing cash reflects the transfer out of the drawer
        $this->assertMoney(7000, $summary['expected_closing_cash']);

        $obligation = RentObligation::sole();
        $this->assertMoney(5000, $obligation->reserved_total);
        $this->assertMoney(0, $obligation->paid_total);
        $this->assertMoney(71000, $obligation->target_amount);
    }

    public function test_reserve_endpoint_requires_an_amount(): void
    {
        $this->fundCanteenCash(8000);

        $this->actingAs($this->staff)->post('/rent/reserve', [])->assertSessionHasErrors('amount');

        $this->assertSame(0, RentReservation::count());
        $this->assertMoney(8000, $this->canteenCash());
    }

    public function test_service_defaults_to_the_suggested_daily_amount(): void
    {
        $this->fundCanteenCash(8000);

        $reservation = app(RentService::class)->reserve([], $this->staff);

        $this->assertMoney(5000, $reservation->amount);
        $this->assertMoney(3000, $this->canteenCash());
        $this->assertMoney(5000, $this->rentReserve());
    }

    public function test_cannot_reserve_more_than_cash_in_hand(): void
    {
        $this->fundCanteenCash(1000);

        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '5000'])->assertSessionHasErrors('amount');

        $this->assertMoney(1000, $this->canteenCash());
        $this->assertMoney(0, $this->rentReserve());
        $this->assertSame(0, RentReservation::count());
    }

    public function test_paying_rent_from_the_reserve_reduces_the_reserve_only(): void
    {
        $this->fundCanteenCash(10000);
        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '6000'])->assertRedirect();

        $this->actingAs($this->staff)->post('/rent/pay', ['amount' => '4000', 'payment_source' => 'rent_reserve'])
            ->assertRedirect();

        $this->assertMoney(2000, $this->rentReserve());
        $this->assertMoney(4000, $this->canteenCash());
        $this->assertMoney(4000, RentObligation::sole()->paid_total);
    }
}
