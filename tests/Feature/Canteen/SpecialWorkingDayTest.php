<?php

namespace Tests\Feature\Canteen;

use App\Enums\DayType;
use App\Models\DailySession;
use App\Models\SchoolCalendar;
use App\Models\Sale;
use App\Services\DailySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_saturday_and_sunday_are_weekend_days_by_default(): void
    {
        $this->assertTrue(\App\Support\WorkingDays::isWeekend('2026-10-31')); // Saturday
        $this->assertTrue(\App\Support\WorkingDays::isWeekend('2026-11-01')); // Sunday
        $this->assertFalse(\App\Support\WorkingDays::isWeekend('2026-10-30')); // Friday

        $this->travelTo(Carbon::parse('2026-10-31 11:00:00'));
        $satSession = app(DailySessionService::class)->getOrCreateForDate('2026-10-31', $this->staff);
        $this->assertSame(DayType::Weekend, $satSession->day_type);
    }

    public function test_dashboard_skips_weekend_dates_to_previous_friday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:00:00'));
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => '100',
            'business_date' => '2026-10-30',
        ])->assertRedirect();

        $this->travelTo(Carbon::parse('2026-11-01 11:00:00'));
        $this->actingAs($this->staff)
            ->get(route('dashboard', ['date' => '2026-11-01']))
            ->assertRedirect(route('dashboard', ['date' => '2026-10-30']));
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
