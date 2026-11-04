<?php

namespace Tests\Feature\Canteen;

use App\Enums\RequiredItemStatus;
use App\Models\RequiredItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class RequiredItemTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_staff_can_add_a_pending_required_item(): void
    {
        $this->actingAs($this->staff)
            ->post(route('required-items.store'), [
                'title' => 'Physics book',
                'class_name' => '2nd Year',
                'quantity_requested' => '2',
                'unit' => 'pcs',
                'notes' => 'Student asked',
            ])
            ->assertRedirect(route('required-items.index', ['filter' => 'pending']));

        $item = RequiredItem::sole();
        $this->assertSame('Physics book', $item->title);
        $this->assertSame('2nd Year', $item->class_name);
        $this->assertSame(RequiredItemStatus::Pending, $item->status);
        $this->assertSame($this->staff->id, $item->created_by);
        $this->assertMoney(2, $item->quantity_requested);
    }

    public function test_staff_can_mark_item_available_with_qty_and_price(): void
    {
        $item = RequiredItem::create([
            'title' => 'A4 copies',
            'status' => RequiredItemStatus::Pending,
            'quantity_requested' => '10',
            'unit' => 'pcs',
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->post(route('required-items.fulfill.store', $item), [
                'quantity_received' => '10',
                'price' => '500',
            ])
            ->assertRedirect(route('required-items.index', ['filter' => 'available']));

        $item->refresh();
        $this->assertSame(RequiredItemStatus::Available, $item->status);
        $this->assertMoney(10, $item->quantity_received);
        $this->assertMoney(500, $item->price);
        $this->assertNotNull($item->fulfilled_at);
        $this->assertSame($this->staff->id, $item->fulfilled_by);
    }

    public function test_staff_cannot_delete_but_admin_can(): void
    {
        $item = RequiredItem::create([
            'title' => 'Glue stick',
            'status' => RequiredItemStatus::Pending,
            'created_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->delete(route('required-items.destroy', $item))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete(route('required-items.destroy', $item))
            ->assertRedirect(route('required-items.index'));

        $this->assertSame(0, RequiredItem::count());
    }
}
