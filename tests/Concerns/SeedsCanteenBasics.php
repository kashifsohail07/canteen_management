<?php

namespace Tests\Concerns;

use App\Enums\CashAccountCode;
use App\Enums\UserRole;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\SaleSegment;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CashLedgerService;
use App\Services\SettingService;
use App\Services\VendorLedgerService;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Minimal reference data every canteen test needs: the three cash accounts, sale segments,
 * settings and (optionally) an admin + staff user. Mirrors DatabaseSeeder but WITHOUT the sample vendors,
 * so each test controls its own vendor balances.
 *
 * Usage (inside a test using RefreshDatabase):
 *   protected function setUp(): void { parent::setUp(); $this->seedCanteenBasics(); }
 */
trait SeedsCanteenBasics
{
    protected User $admin;

    protected User $staff;

    /** Create cash accounts, sale segments, settings, expense category; optionally admin/staff users. */
    protected function seedCanteenBasics(bool $withUsers = true): void
    {
        // Views use @vite(); tests must not depend on a compiled frontend build.
        $this->withoutVite();

        foreach (CashAccountCode::cases() as $code) {
            CashAccount::firstOrCreate(['code' => $code->value], ['name' => $code->label(), 'balance' => '0.00']);
        }

        foreach (['First Break', 'Second Break', 'School Closing / Off Time', 'Other Sale'] as $i => $name) {
            SaleSegment::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1, 'is_active' => true, 'is_rent_side' => false],
            );
        }

        SaleSegment::updateOrCreate(
            ['slug' => 'rent-side'],
            ['name' => 'Rent Side', 'sort_order' => 5, 'is_active' => true, 'is_rent_side' => true],
        );

        $settings = app(SettingService::class);
        $settings->set('monthly_rent_target', '71000');
        $settings->set('daily_rent_suggest', '5000');
        $settings->set('currency', 'PKR');

        ExpenseCategory::firstOrCreate(['name' => 'Misc'], ['is_active' => true]);

        if ($withUsers) {
            $this->admin = User::factory()->admin()->create(['email' => 'admin@canteen.local', 'name' => 'Admin']);
            $this->staff = User::factory()->staff()->create(['email' => 'staff@canteen.local', 'name' => 'Staff']);
        }
    }

    /** Create a vendor through the ledger service (so an opening-balance ledger entry exists when non-zero). */
    protected function makeVendor(string $name = 'Test Vendor', string|int|float $opening = 0): Vendor
    {
        return app(VendorLedgerService::class)->createVendor([
            'name' => $name,
            'opening_balance' => $opening,
        ], $this->admin ?? null);
    }

    protected function segment(string $slug = 'first-break'): SaleSegment
    {
        return SaleSegment::where('slug', $slug)->firstOrFail();
    }

    /**
     * Put cash in the drawer without creating a day session (so opening cash of the first session includes it).
     */
    protected function fundCanteenCash(string|int|float $amount): void
    {
        app(CashLedgerService::class)->credit(CashAccountCode::CanteenCash, $amount, 'test_funding', ['allow_negative' => true]);
    }

    protected function balanceOf(CashAccountCode $code): string
    {
        return Money::of(CashAccount::where('code', $code->value)->value('balance'));
    }

    protected function canteenCash(): string
    {
        return $this->balanceOf(CashAccountCode::CanteenCash);
    }

    protected function rentReserve(): string
    {
        return $this->balanceOf(CashAccountCode::RentReserve);
    }

    protected function ownerRecoverable(): string
    {
        return $this->balanceOf(CashAccountCode::OwnerRecoverable);
    }

    protected function vendorBalance(Vendor $vendor): string
    {
        return Money::of(Vendor::withTrashed()->whereKey($vendor->id)->value('current_balance'));
    }

    /** Compare money values numerically ("500" equals "500.00"). */
    protected function assertMoney(string|int|float $expected, string|int|float|null $actual, string $message = ''): void
    {
        $this->assertSame(Money::of($expected), Money::of($actual), $message);
    }

    /**
     * Payload for POST /closing — fills reasons for empty sale segments + missing rent
     * so day-close checklist validation passes in tests.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function closeDayPayload(string|int|float $actualCash, ?string $date = null, array $extra = []): array
    {
        $payload = array_merge([
            'business_date' => $date ?? now()->toDateString(),
            'actual_counted_cash' => (string) $actualCash,
            'rent_reason' => 'Test rent skip',
            'segment_reasons' => [],
        ], $extra);

        foreach (SaleSegment::where('is_active', true)->pluck('id') as $id) {
            $payload['segment_reasons'][$id] ??= 'Test: no sale in segment';
        }

        return $payload;
    }
}
