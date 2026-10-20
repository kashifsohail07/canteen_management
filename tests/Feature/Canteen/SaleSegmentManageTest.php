<?php

namespace Tests\Feature\Canteen;

use App\Models\SaleSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class SaleSegmentManageTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_admin_can_open_segments_page(): void
    {
        $this->actingAs($this->admin)->get('/segments')->assertOk()->assertSee('Sale Segments');
    }

    public function test_staff_cannot_manage_segments(): void
    {
        $this->actingAs($this->staff)->get('/segments')->assertForbidden();
        $this->actingAs($this->staff)->post('/segments', ['name' => 'Hack'])->assertForbidden();
    }

    public function test_admin_can_add_a_custom_segment(): void
    {
        $this->actingAs($this->admin)->post('/segments', ['name' => 'Third Break'])
            ->assertRedirect(route('segments.index'));

        $this->assertDatabaseHas('sale_segments', [
            'name' => 'Third Break',
            'is_active' => 1,
        ]);

        $this->actingAs($this->staff)->get('/sales/create')
            ->assertOk()
            ->assertSee('Third Break');
    }

    public function test_admin_can_rename_and_deactivate_segment(): void
    {
        $segment = $this->segment();

        $this->actingAs($this->admin)->put('/segments/'.$segment->id, [
            'name' => 'Pehli Break',
            'is_active' => 0,
        ])->assertRedirect(route('segments.index'));

        $segment->refresh();
        $this->assertSame('Pehli Break', $segment->name);
        $this->assertFalse($segment->is_active);
    }

    public function test_admin_can_reorder_segments(): void
    {
        $first = SaleSegment::orderBy('sort_order')->first();
        $second = SaleSegment::orderBy('sort_order')->skip(1)->first();
        $this->assertNotNull($second);

        $this->actingAs($this->admin)->post('/segments/'.$second->id.'/move', ['direction' => 'up'])
            ->assertRedirect(route('segments.index'));

        $this->assertSame($second->id, SaleSegment::orderBy('sort_order')->orderBy('id')->value('id'));
        $this->assertSame(1, (int) $second->fresh()->sort_order);
        $this->assertSame(2, (int) $first->fresh()->sort_order);
    }

    public function test_deleting_segment_with_sales_deactivates_instead(): void
    {
        $segment = $this->segment();
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $segment->id,
            'amount' => '500',
        ])->assertRedirect();

        $this->actingAs($this->admin)->delete('/segments/'.$segment->id)
            ->assertRedirect(route('segments.index'));

        $this->assertDatabaseHas('sale_segments', ['id' => $segment->id, 'is_active' => 0]);
    }
}
