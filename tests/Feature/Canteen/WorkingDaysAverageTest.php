<?php

namespace Tests\Feature\Canteen;

use App\Enums\DayType;
use App\Enums\DailySessionStatus;
use App\Models\DailySession;
use App\Models\Sale;
use App\Services\DashboardService;
use App\Support\WorkingDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class WorkingDaysAverageTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_month_sales_average_ignores_weekend_sales(): void
    {
        $this->assertTrue(WorkingDays::isClosed('2026-10-31'));
        $this->assertFalse(WorkingDays::isClosed('2026-10-30'));

        $friday = DailySession::create([
            'business_date' => '2026-10-30',
            'day_type' => DayType::Normal,
            'status' => DailySessionStatus::Closed,
            'opening_canteen_cash' => '0.00',
            'opening_rent_reserve' => '0.00',
            'opening_owner_recoverable' => '0.00',
            'created_by' => $this->staff->id,
        ]);
        $saturday = DailySession::create([
            'business_date' => '2026-10-31',
            'day_type' => DayType::Weekend,
            'status' => DailySessionStatus::Closed,
            'opening_canteen_cash' => '0.00',
            'opening_rent_reserve' => '0.00',
            'opening_owner_recoverable' => '0.00',
            'created_by' => $this->staff->id,
        ]);

        Sale::create([
            'daily_session_id' => $friday->id,
            'sale_segment_id' => $this->segment()->id,
            'amount' => '10000.00',
            'payment_method' => 'cash',
            'recorded_at' => '2026-10-30 10:00:00',
            'created_by' => $this->staff->id,
        ]);
        Sale::create([
            'daily_session_id' => $saturday->id,
            'sale_segment_id' => $this->segment()->id,
            'amount' => '9000.00',
            'payment_method' => 'cash',
            'recorded_at' => '2026-10-31 10:00:00',
            'created_by' => $this->staff->id,
        ]);

        $stats = app(DashboardService::class)->monthSalesStats(2026, 10);

        $this->assertMoney(10000, $stats['total']);
        $this->assertMoney(10000, $stats['average']);
        $this->assertSame(1, $stats['days_with_sales']);
    }
}
