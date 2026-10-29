<?php

namespace Tests\Feature\Canteen;

use App\Models\Sale;
use App\Services\DailySessionService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class MultipleSalesInBreakTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_multiple_sales_in_the_same_segment_add_up_and_increase_cash(): void
    {
        $first = $this->segment('first-break');
        $second = $this->segment('second-break');

        foreach ([1200, 800, 450] as $amount) {
            $this->actingAs($this->staff)->post('/sales', [
                'sale_segment_id' => $first->id,
                'amount' => $amount,
            ])->assertRedirect(route('dashboard'));
        }
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $second->id, 'amount' => '300'])->assertRedirect();

        $this->assertSame(4, Sale::count());
        $this->assertMoney(2750, $this->canteenCash());

        $session = app(DailySessionService::class)->findForDate(now());
        $totals = app(DashboardService::class)->segmentTotals($session)->keyBy('slug');

        $this->assertMoney(2450, $totals['first-break']['total']);
        $this->assertSame(3, $totals['first-break']['count']);
        $this->assertMoney(300, $totals['second-break']['total']);
        $this->assertMoney(0, $totals['other-sale']['total']);

        $this->assertMoney(2750, app(DashboardService::class)->summary($session)['sales_today']);
    }

    public function test_sale_validation(): void
    {
        $segment = $this->segment();

        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => '0'])->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $segment->id, 'amount' => 'abc'])->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post('/sales', ['amount' => '100'])->assertSessionHasErrors('sale_segment_id');
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => 9999, 'amount' => '100'])->assertSessionHasErrors('sale_segment_id');

        $this->assertSame(0, Sale::count());
        $this->assertMoney(0, $this->canteenCash());
    }

    public function test_future_business_date_is_rejected(): void
    {
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => '100',
            'business_date' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('business_date');
    }
}
