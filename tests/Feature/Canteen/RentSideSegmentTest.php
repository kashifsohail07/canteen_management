<?php

namespace Tests\Feature\Canteen;

use App\Models\RentReservation;
use App\Models\Sale;
use App\Models\SaleSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class RentSideSegmentTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_rent_side_segment_moves_cash_to_rent_reserve_not_sales_total(): void
    {
        $this->fundCanteenCash(10000);
        $rent = SaleSegment::where('is_rent_side', true)->firstOrFail();

        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $rent->id,
            'amount' => '5000',
        ])->assertRedirect();

        $this->assertMoney(5000, $this->canteenCash());
        $this->assertMoney(5000, $this->rentReserve());
        $this->assertSame(1, RentReservation::count());
        $this->assertSame(1, Sale::where('payment_method', 'rent_side')->count());

        $session = \App\Models\DailySession::sole();
        $summary = app(\App\Services\DailyClosingService::class)->summarize($session);
        $this->assertMoney(0, $summary['sales_total']); // rent is not bikri
        $this->assertMoney(5000, $summary['rent_reserved']);
    }

    public function test_add_sale_screen_lists_rent_side_pill(): void
    {
        $this->actingAs($this->staff)->get('/sales/create')
            ->assertOk()
            ->assertSee('Rent Side');
    }
}
