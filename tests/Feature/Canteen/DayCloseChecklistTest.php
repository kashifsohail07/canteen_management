<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\DailySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class DayCloseChecklistTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_cannot_close_without_reasons_when_segments_and_rent_empty(): void
    {
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => '100',
        ])->assertRedirect();

        $today = now()->toDateString();

        $this->actingAs($this->admin)->post('/closing', [
            'business_date' => $today,
            'actual_counted_cash' => '100',
        ])->assertSessionHasErrors();

        $this->assertTrue(DailySession::sole()->isOpen());
    }

    public function test_close_succeeds_when_empty_segment_and_rent_have_reasons(): void
    {
        // Only First Break has a sale — other active segments need reasons; rent needs reason.
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => '500',
        ])->assertRedirect();

        $today = now()->toDateString();
        $empty = \App\Models\SaleSegment::where('is_active', true)
            ->where('id', '!=', $this->segment()->id)
            ->pluck('id');

        $payload = [
            'business_date' => $today,
            'actual_counted_cash' => '500',
            'rent_reason' => 'Sale kam thi aaj',
            'segment_reasons' => [],
        ];
        foreach ($empty as $id) {
            $payload['segment_reasons'][$id] = 'Is break mein sale nahi';
        }

        $this->actingAs($this->admin)->post('/closing', $payload)->assertRedirect();

        $session = DailySession::sole();
        $this->assertTrue($session->isClosed());
        $this->assertSame('Sale kam thi aaj', $session->close_checklist['rent']);
        $this->assertNotEmpty($session->close_checklist['segments']);
        $this->assertSame(1, ActivityLog::where('activity_type', 'rent_skip')->count());
    }

    public function test_rent_reserve_clears_rent_reason_requirement(): void
    {
        $this->fundCanteenCash(10000);

        foreach (\App\Models\SaleSegment::where('is_active', true)->get() as $seg) {
            $this->actingAs($this->staff)->post('/sales', [
                'sale_segment_id' => $seg->id,
                'amount' => '100',
            ])->assertRedirect();
        }

        $this->actingAs($this->staff)->post('/rent/reserve', ['amount' => '5000'])->assertRedirect();

        $today = now()->toDateString();
        $salesTotal = 100 * \App\Models\SaleSegment::where('is_active', true)->count();
        $expectedCash = 10000 + $salesTotal - 5000;

        $this->actingAs($this->admin)->post('/closing', [
            'business_date' => $today,
            'actual_counted_cash' => (string) $expectedCash,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(DailySession::sole()->isClosed());
        $this->assertNull(DailySession::sole()->close_checklist['rent'] ?? null);
    }

    public function test_admin_can_update_daily_rent_from_segments_page(): void
    {
        $this->actingAs($this->admin)->put(route('segments.rent.update'), [
            'daily_rent_suggest' => '4500',
        ])->assertRedirect(route('segments.index'));

        $this->assertSame('4500.00', app(\App\Services\SettingService::class)->getDecimal('daily_rent_suggest', 0));
    }
}
