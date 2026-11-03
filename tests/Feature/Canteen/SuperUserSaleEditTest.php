<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class SuperUserSaleEditTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
        $this->super = User::factory()->superUser()->create([
            'email' => 'super@canteen.local',
            'name' => 'Super User',
        ]);
    }

    public function test_super_user_sees_edit_form_on_sale_activity(): void
    {
        $sale = $this->postSale('1000');
        $activity = ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->sole();

        $this->actingAs($this->super)
            ->get(route('activity.show', $activity))
            ->assertOk()
            ->assertSee('Sale Edit (Super User)')
            ->assertSee(route('sales.update', $sale), false);
    }

    public function test_admin_and_staff_do_not_see_sale_edit_form(): void
    {
        $sale = $this->postSale('1000');
        $activity = ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->sole();

        $this->actingAs($this->admin)
            ->get(route('activity.show', $activity))
            ->assertOk()
            ->assertDontSee('Sale Edit (Super User)');

        $this->actingAs($this->staff)
            ->get(route('activity.show', $activity))
            ->assertOk()
            ->assertDontSee('Sale Edit (Super User)');
    }

    public function test_super_user_can_edit_sale_amount_and_cash_adjusts(): void
    {
        $sale = $this->postSale('1000');
        $this->assertMoney(1000, $this->canteenCash());

        $this->actingAs($this->super)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '1500',
            'reason' => 'Amount galat thi',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertMoney(1500, $sale->amount);
        $this->assertMoney(1500, $this->canteenCash());

        $activity = ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->sole();
        $this->assertMoney(1500, $activity->amount);

        $audit = AuditLog::where('action', 'sale.updated')->sole();
        $this->assertSame($this->super->id, $audit->user_id);
        $this->assertSame('Amount galat thi', $audit->reason);
        $this->assertMoney(1000, $audit->old_values['amount']);
        $this->assertMoney(1500, $audit->new_values['amount']);
    }

    public function test_super_user_can_change_segment(): void
    {
        $sale = $this->postSale('500');
        $other = $this->segment('second-break');

        $this->actingAs($this->super)->put(route('sales.update', $sale), [
            'sale_segment_id' => $other->id,
            'amount' => '500',
            'reason' => 'Wrong break selected',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame($other->id, $sale->sale_segment_id);

        $activity = ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->sole();
        $this->assertSame($other->name, $activity->title);
    }

    public function test_admin_cannot_edit_sale_via_http(): void
    {
        $sale = $this->postSale('800');

        $this->actingAs($this->admin)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '900',
            'reason' => 'trying as admin',
        ])->assertForbidden();

        $this->assertMoney(800, $sale->fresh()->amount);
        $this->assertSame(0, AuditLog::where('action', 'sale.updated')->count());
    }

    public function test_staff_cannot_edit_sale_via_http(): void
    {
        $sale = $this->postSale('800');

        $this->actingAs($this->staff)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '900',
            'reason' => 'trying as staff',
        ])->assertForbidden();
    }

    public function test_reason_is_required(): void
    {
        $sale = $this->postSale('800');

        $this->actingAs($this->super)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '900',
        ])->assertSessionHasErrors('reason');
    }

    public function test_super_user_can_zero_out_a_mistaken_sale(): void
    {
        $sale = $this->postSale('2500');
        $this->assertMoney(2500, $this->canteenCash());

        $this->actingAs($this->super)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '0',
            'reason' => 'Us waqt sale zero thi',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertMoney(0, $sale->amount);
        $this->assertMoney(0, $this->canteenCash());

        // Zeroed sales must leave the activity feed (amount already reversed from cash).
        $this->assertSame(0, ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->count());
    }

    public function test_super_user_sees_delete_form_on_sale_activity(): void
    {
        $sale = $this->postSale('1000');
        $activity = ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->sole();

        $this->actingAs($this->super)
            ->get(route('activity.show', $activity))
            ->assertOk()
            ->assertSee('Delete Sale')
            ->assertSee(route('sales.destroy', $sale), false);
    }

    public function test_super_user_can_delete_a_duplicate_sale(): void
    {
        $sale = $this->postSale('3000');
        $this->assertMoney(3000, $this->canteenCash());
        $this->assertSame(1, ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->count());

        $this->actingAs($this->super)
            ->delete(route('sales.destroy', $sale), ['reason' => 'Double entry by mistake'])
            ->assertRedirect(route('dashboard'));

        $sale->refresh();
        $this->assertTrue($sale->is_reversed);
        $this->assertSame($this->super->id, $sale->reversed_by);
        $this->assertSame('Double entry by mistake', $sale->reversal_reason);
        $this->assertMoney(0, $this->canteenCash());
        $this->assertSame(0, ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->count());

        $audit = AuditLog::where('action', 'sale.deleted')->sole();
        $this->assertSame($this->super->id, $audit->user_id);
        $this->assertSame('Double entry by mistake', $audit->reason);
    }

    public function test_admin_cannot_delete_sale(): void
    {
        $sale = $this->postSale('800');

        $this->actingAs($this->admin)
            ->delete(route('sales.destroy', $sale), ['reason' => 'trying as admin'])
            ->assertForbidden();

        $this->assertFalse($sale->fresh()->is_reversed);
        $this->assertMoney(800, $this->canteenCash());
        $this->assertSame(1, ActivityLog::where('reference_type', 'sale')->where('reference_id', $sale->id)->count());
    }

    public function test_cannot_delete_sale_on_closed_day(): void
    {
        $sale = $this->postSale('100');
        $today = now()->toDateString();

        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('100', $today))->assertRedirect();

        $this->actingAs($this->super)
            ->delete(route('sales.destroy', $sale), ['reason' => 'after close'])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($sale->fresh()->is_reversed);
        $this->assertMoney(100, $this->canteenCash());
    }

    public function test_cannot_edit_sale_on_closed_day(): void
    {
        $sale = $this->postSale('100');
        $today = now()->toDateString();

        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('100', $today))->assertRedirect();

        $this->actingAs($this->super)->put(route('sales.update', $sale), [
            'sale_segment_id' => $sale->sale_segment_id,
            'amount' => '200',
            'reason' => 'after close',
        ])->assertSessionHasErrors('amount');

        $this->assertMoney(100, $sale->fresh()->amount);
    }

    private function postSale(string $amount): Sale
    {
        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => $amount,
        ])->assertRedirect();

        return Sale::latest('id')->firstOrFail();
    }
}
