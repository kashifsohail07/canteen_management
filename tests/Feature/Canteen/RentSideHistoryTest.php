<?php

namespace Tests\Feature\Canteen;

use App\Models\RentObligation;
use App\Services\RentService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class RentSideHistoryTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_each_month_keeps_its_own_reserved_total_and_earlier_months_close(): void
    {
        $this->fundCanteenCash(100000);
        $rent = app(RentService::class);

        Carbon::setTestNow('2026-10-15 10:00:00');
        $rent->reserve(['amount' => '30000', 'business_date' => '2026-10-15'], $this->staff);

        $october = RentObligation::where('year', 2026)->where('month', 10)->sole();
        $this->assertMoney(30000, $october->reserved_total);
        $this->assertNull($october->closed_at);

        // Day must be closed before a new calendar month can take entries.
        $this->actingAs($this->admin)
            ->post('/closing', $this->closeDayPayload('70000', '2026-10-15'))
            ->assertRedirect();

        Carbon::setTestNow('2026-11-02 10:00:00');
        $rent->reserve(['amount' => '5000', 'business_date' => '2026-11-02'], $this->staff);

        $october->refresh();
        $november = RentObligation::where('year', 2026)->where('month', 11)->sole();

        $this->assertNotNull($october->closed_at, 'October should soft-close when November starts');
        $this->assertMoney(30000, $october->reserved_total);
        $this->assertMoney(5000, $november->reserved_total);
        $this->assertNull($november->closed_at);
        // Live cash bucket still holds both months' side money until paid.
        $this->assertMoney(35000, $this->rentReserve());
    }

    public function test_history_page_shows_per_month_totals_and_average(): void
    {
        $this->fundCanteenCash(100000);
        $rent = app(RentService::class);

        Carbon::setTestNow('2026-10-10 10:00:00');
        $rent->reserve(['amount' => '30000', 'business_date' => '2026-10-10'], $this->staff);
        $this->actingAs($this->admin)
            ->post('/closing', $this->closeDayPayload('70000', '2026-10-10'))
            ->assertRedirect();

        Carbon::setTestNow('2026-11-05 10:00:00');
        $rent->reserve(['amount' => '10000', 'business_date' => '2026-11-05'], $this->staff);

        $data = app(ReportService::class)->rentSideHistory();

        $this->assertMoney(40000, $data['total_reserved']);
        $this->assertMoney(20000, $data['average_reserved']); // (30000+10000)/2
        $this->assertSame(2, $data['months_with_side']);
        $this->assertMoney(10000, $data['current_reserved']);

        $this->actingAs($this->staff)
            ->get(route('reports.rent-side'))
            ->assertOk()
            ->assertSee('October 2026')
            ->assertSee('November 2026')
            ->assertSee('Closed')
            ->assertSee('Open');
    }

    public function test_opening_history_in_new_month_closes_previous_even_without_new_side(): void
    {
        $this->fundCanteenCash(50000);
        $rent = app(RentService::class);

        Carbon::setTestNow('2026-10-20 10:00:00');
        $rent->reserve(['amount' => '12000', 'business_date' => '2026-10-20'], $this->staff);

        Carbon::setTestNow('2026-11-01 09:00:00');
        $data = app(ReportService::class)->rentSideHistory();

        $october = RentObligation::where('year', 2026)->where('month', 10)->sole();
        $this->assertNotNull($october->closed_at);
        $this->assertMoney(12000, $october->reserved_total);
        $this->assertMoney(0, $data['current_reserved']);
        $this->assertSame('November 2026', $data['current_label']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
