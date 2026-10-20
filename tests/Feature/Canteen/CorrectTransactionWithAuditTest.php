<?php

namespace Tests\Feature\Canteen;

use App\Models\AuditLog;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\VendorLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class CorrectTransactionWithAuditTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_admin_adjust_balance_via_service_creates_audit_log_and_ledger_entry(): void
    {
        $vendor = $this->makeVendor('Samosay Supplier', 14000);

        $entry = app(VendorLedgerService::class)->adjustBalance($vendor, '13500', 'Vendor confirmed lower total', $this->admin);

        $this->assertMoney(13500, $this->vendorBalance($vendor));
        $this->assertSame('adjustment', $entry->entry_type);
        $this->assertMoney(500, $entry->credit);
        $this->assertMoney(13500, $entry->balance_after);

        $audit = AuditLog::where('action', 'vendor.balance_adjusted')->sole();
        $this->assertSame(Vendor::class, $audit->auditable_type);
        $this->assertSame($vendor->id, $audit->auditable_id);
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame('Vendor confirmed lower total', $audit->reason);
        $this->assertMoney(14000, $audit->old_values['current_balance']);
        $this->assertMoney(13500, $audit->new_values['current_balance']);
    }

    public function test_admin_adjust_balance_via_http_creates_audit_log(): void
    {
        $vendor = $this->makeVendor('Biscuits', 5000);

        $this->actingAs($this->admin)->post("/vendors/{$vendor->id}/adjust-balance", [
            'new_balance' => '6200.50',
            'reason' => 'Missed bill from last month',
        ])->assertRedirect(route('vendors.show', $vendor));

        $this->assertMoney('6200.50', $this->vendorBalance($vendor));
        $this->assertSame(1, AuditLog::where('action', 'vendor.balance_adjusted')->count());
        $this->assertSame('adjustment', VendorLedgerEntry::where('vendor_id', $vendor->id)->latest('id')->first()->entry_type);
        // adjustments never touch the drawer
        $this->assertMoney(0, $this->canteenCash());
    }

    public function test_adjustment_to_negative_balance_is_allowed(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);

        $this->actingAs($this->admin)->post("/vendors/{$vendor->id}/adjust-balance", [
            'new_balance' => '-300',
            'reason' => 'Advance recorded manually',
        ])->assertRedirect();

        $this->assertMoney(-300, $this->vendorBalance($vendor));
    }

    public function test_reason_is_mandatory(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);

        $this->actingAs($this->admin)->post("/vendors/{$vendor->id}/adjust-balance", ['new_balance' => '900'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, AuditLog::count());
        $this->assertMoney(1000, $this->vendorBalance($vendor));

        $this->expectException(ValidationException::class);
        app(VendorLedgerService::class)->adjustBalance($vendor, '900', '   ', $this->admin);
    }

    public function test_service_refuses_non_admins(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);

        try {
            app(VendorLedgerService::class)->adjustBalance($vendor, '1', 'sneaky', $this->staff);
            $this->fail('Staff must not be able to adjust balances.');
        } catch (AuthorizationException) {
            $this->assertSame(0, AuditLog::count());
            $this->assertMoney(1000, $this->vendorBalance($vendor));
        }
    }

    public function test_changing_opening_balance_is_audited(): void
    {
        $vendor = $this->makeVendor('Biscuits', 1000);

        $this->actingAs($this->admin)->post("/vendors/{$vendor->id}/opening-balance", [
            'opening_balance' => '1200',
            'reason' => 'Typo at setup',
        ])->assertRedirect();

        $audit = AuditLog::where('action', 'vendor.opening_balance_changed')->sole();
        $this->assertMoney(1000, $audit->old_values['opening_balance']);
        $this->assertMoney(1200, $audit->new_values['opening_balance']);
        $this->assertSame('Typo at setup', $audit->reason);
    }

    public function test_closing_and_reopening_a_day_are_audited(): void
    {
        $this->actingAs($this->admin)->post('/sales', ['sale_segment_id' => $this->segment()->id, 'amount' => '100'])->assertRedirect();
        $today = now()->toDateString();

        $this->actingAs($this->admin)->post('/closing', $this->closeDayPayload('100', $today))->assertRedirect();
        $this->actingAs($this->admin)->post('/closing/reopen', ['business_date' => $today, 'reason' => 'Mistake'])->assertRedirect();

        $this->assertSame(1, AuditLog::where('action', 'daily_session.closed')->count());
        $this->assertSame('Mistake', AuditLog::where('action', 'daily_session.reopened')->sole()->reason);
    }
}
