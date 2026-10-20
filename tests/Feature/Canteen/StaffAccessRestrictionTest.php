<?php

namespace Tests\Feature\Canteen;

use App\Models\AuditLog;
use App\Models\DailySession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class StaffAccessRestrictionTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_staff_cannot_open_or_change_settings(): void
    {
        $this->actingAs($this->staff)->get('/settings')->assertForbidden();
        $this->actingAs($this->staff)->put('/settings', [
            'monthly_rent_target' => '1',
            'daily_rent_suggest' => '1',
        ])->assertForbidden();

        $this->assertSame('71000', app(\App\Services\SettingService::class)->get('monthly_rent_target'));
    }

    public function test_admin_can_open_settings(): void
    {
        $this->actingAs($this->admin)->get('/settings')->assertOk();
    }

    public function test_super_user_has_full_privileged_edit_access(): void
    {
        $super = User::factory()->superUser()->create([
            'email' => 'super@canteen.local',
            'name' => 'Super User',
        ]);
        $vendor = $this->makeVendor('Biscuits', 5000);

        $this->assertTrue($super->isSuperUser());
        $this->assertTrue($super->isAdmin());

        $this->actingAs($super)->get('/settings')->assertOk();
        $this->actingAs($super)->get('/segments')->assertOk();
        $this->actingAs($super)->get('/setup/vendors')->assertOk();

        $this->actingAs($super)->post("/vendors/{$vendor->id}/adjust-balance", [
            'new_balance' => '4000',
            'reason' => 'Super user correction',
        ])->assertRedirect();

        $vendor->refresh();
        $this->assertMoney(4000, $vendor->current_balance);
    }

    public function test_staff_cannot_reopen_a_closed_day(): void
    {
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '100'])->assertRedirect();
        $today = now()->toDateString();
        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('100', $today))->assertRedirect();

        $this->actingAs($this->staff)->post('/closing/reopen', ['business_date' => $today, 'reason' => 'please'])->assertForbidden();

        $this->assertTrue(DailySession::sole()->isClosed());
    }

    public function test_staff_cannot_close_a_day(): void
    {
        $this->actingAs($this->staff)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '100'])->assertRedirect();

        $this->actingAs($this->staff)->post('/closing', [
            'business_date' => now()->toDateString(),
            'actual_counted_cash' => '100',
        ])->assertForbidden();

        $this->assertTrue(DailySession::sole()->isOpen());
    }

    public function test_staff_cannot_adjust_opening_balance_or_vendor_balance(): void
    {
        $vendor = $this->makeVendor('Biscuits', 5000);

        $this->actingAs($this->staff)->post("/vendors/{$vendor->id}/opening-balance", [
            'opening_balance' => '1', 'reason' => 'trying',
        ])->assertForbidden();

        $this->actingAs($this->staff)->post("/vendors/{$vendor->id}/adjust-balance", [
            'new_balance' => '1', 'reason' => 'trying',
        ])->assertForbidden();

        $vendor->refresh();
        $this->assertMoney(5000, $vendor->current_balance);
        $this->assertMoney(5000, $vendor->opening_balance);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_staff_cannot_use_the_setup_wizard(): void
    {
        $this->actingAs($this->staff)->get('/setup/vendors')->assertForbidden();
    }

    public function test_inactive_staff_session_is_logged_out(): void
    {
        $this->staff->update(['is_active' => false]);

        $this->actingAs($this->staff)->get('/sales/create')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_can_do_daily_entry_work(): void
    {
        $this->actingAs($this->staff)->get('/sales/create')->assertOk();
        $this->actingAs($this->staff)->get('/purchases/create')->assertOk();
        $this->actingAs($this->staff)->get('/payments/create')->assertOk();
        $this->actingAs($this->staff)->get('/vendors')->assertOk();
        $this->actingAs($this->staff)->get('/closing')->assertOk();
    }
}
