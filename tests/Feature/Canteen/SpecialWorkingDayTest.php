<?php

namespace Tests\Feature\Canteen;

use App\Enums\DayType;
use App\Models\DailySession;
use App\Models\SchoolCalendar;
use App\Models\Sale;
use App\Services\DailySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class SpecialWorkingDayTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_ptm_day_session_is_created_and_allows_transactions(): void
    {
        SchoolCalendar::create([
            'date' => now()->toDateString(),
            'day_type' => 'ptm',
            'label' => 'Parent Teacher Meeting',
            'is_working_day' => true,
        ]);

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '2500'])
            ->assertRedirect(route('dashboard'));

        $session = DailySession::sole();
        $this->assertSame(DayType::Ptm, $session->day_type);
        $this->assertTrue($session->isOpen());
        $this->assertSame(1, Sale::count());
        $this->assertMoney(2500, $this->canteenCash());

        // purchases and payments work on the PTM day too
        $vendor = $this->makeVendor('Samosay Supplier', 0);
        $this->actingAs($this->staff)->post('/purchases', ['vendor_id' => $vendor->id, 'bill_amount' => '900'])->assertRedirect();
        $this->assertMoney(900, $this->vendorBalance($vendor));
    }

    public function test_special_day_type_is_detected_from_the_calendar(): void
    {
        SchoolCalendar::create(['date' => now()->toDateString(), 'day_type' => 'special', 'label' => 'Sports Day', 'is_working_day' => true]);

        $session = app(DailySessionService::class)->getOrCreateForDate(now(), $this->staff);

        $this->assertSame(DayType::Special, $session->day_type);
    }

    public function test_sunday_is_a_weekend_day_by_default_but_still_accepts_entries(): void
    {
        $sunday = now()->startOfWeek()->subDay(); // the most recent past Sunday
        $this->travelTo($sunday->setTime(11, 0));

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '100'])->assertRedirect();

        $session = DailySession::sole();
        $this->assertSame(DayType::Weekend, $session->day_type);
        $this->assertMoney(100, $this->canteenCash());
    }

    public function test_ptm_on_a_weekend_overrides_the_weekend_type(): void
    {
        $sunday = now()->startOfWeek()->subDay(); // the most recent past Sunday
        $this->travelTo($sunday->setTime(11, 0));
        SchoolCalendar::create(['date' => $sunday->toDateString(), 'day_type' => 'ptm', 'is_working_day' => true]);

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '100'])->assertRedirect();

        $this->assertSame(DayType::Ptm, DailySession::sole()->day_type);
    }
}
