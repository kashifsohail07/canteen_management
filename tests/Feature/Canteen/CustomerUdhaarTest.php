<?php

namespace Tests\Feature\Canteen;

use App\Models\ActivityLog;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Services\DailySessionService;
use App\Services\DashboardService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class CustomerUdhaarTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_customer_created_with_opening_balance(): void
    {
        $response = $this->actingAs($this->admin)->post('/customers', [
            'name' => 'Sir Ali',
            'phone' => '0300-1111111',
            'opening_balance' => '500',
        ]);

        $customer = Customer::where('name', 'Sir Ali')->firstOrFail();
        $response->assertRedirect(route('customers.show', $customer));

        $this->assertMoney(500, $customer->current_balance);
        $this->assertMoney(500, $customer->opening_balance);
        $this->assertTrue($customer->opening_balance_locked);

        $entry = CustomerLedgerEntry::where('customer_id', $customer->id)->sole();
        $this->assertSame('opening_balance', $entry->entry_type);
        $this->assertMoney(500, $entry->debit);
    }

    public function test_udhaar_increases_balance_without_sale_or_sales_today(): void
    {
        $customer = $this->makeCustomer('Miss Sara', 0);
        $this->fundCanteenCash(1000);

        $this->actingAs($this->staff)->post('/sales', [
            'sale_segment_id' => $this->segment()->id,
            'amount' => '300',
        ])->assertRedirect();

        $session = app(DailySessionService::class)->findForDate(now());
        $salesBefore = app(DashboardService::class)->summary($session)['sales_today'];
        $cashBefore = $this->canteenCash();
        $saleCountBefore = Sale::count();

        $this->actingAs($this->staff)->post(route('customers.udhaar.store'), [
            'customer_id' => $customer->id,
            'amount' => '200',
            'notes' => '5 rolls',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertMoney(200, $customer->current_balance);

        $entry = CustomerLedgerEntry::where('customer_id', $customer->id)->where('entry_type', 'udhaar')->sole();
        $this->assertMoney(200, $entry->debit);
        $this->assertSame('5 rolls', $entry->notes);

        $this->assertSame($saleCountBefore, Sale::count());
        $this->assertMoney($salesBefore, app(DashboardService::class)->summary($session->fresh())['sales_today']);
        $this->assertMoney($cashBefore, $this->canteenCash());

        $activity = ActivityLog::where('activity_type', 'customer_udhaar')->sole();
        $this->assertSame('Miss Sara', $activity->title);
        $this->assertMoney(200, $activity->amount);

        $this->assertMoney(200, app(DashboardService::class)->summary($session->fresh())['customers_owed']);
    }

    public function test_payment_decreases_balance_and_credits_canteen_cash(): void
    {
        $customer = $this->makeCustomer('Sir Bilal', 200);
        $cashBefore = $this->canteenCash();

        $this->actingAs($this->staff)->post(route('customers.payment.store'), [
            'customer_id' => $customer->id,
            'amount' => '200',
            'notes' => 'Full clear',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertMoney(0, $customer->current_balance);
        $this->assertMoney(Money::add($cashBefore, 200), $this->canteenCash());

        $entry = CustomerLedgerEntry::where('customer_id', $customer->id)->where('entry_type', 'payment')->sole();
        $this->assertMoney(200, $entry->credit);

        $cashTx = CashTransaction::where('transaction_type', 'customer_payment')->sole();
        $this->assertMoney(200, $cashTx->amount);

        $activity = ActivityLog::where('activity_type', 'customer_payment')->sole();
        $this->assertSame('Sir Bilal', $activity->title);
        $this->assertMoney(200, $activity->amount);
    }

    public function test_overpay_creates_negative_advance_balance(): void
    {
        $customer = $this->makeCustomer('Miss Nadia', 100);

        $this->actingAs($this->staff)->post(route('customers.payment.store'), [
            'customer_id' => $customer->id,
            'amount' => '250',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertMoney(-150, $customer->current_balance);
        $this->assertMoney(250, $this->canteenCash());
    }

    public function test_udhaar_requires_notes(): void
    {
        $customer = $this->makeCustomer('Teacher', 0);

        $this->actingAs($this->staff)->post(route('customers.udhaar.store'), [
            'customer_id' => $customer->id,
            'amount' => '50',
        ])->assertSessionHasErrors('notes');

        $this->assertSame(0, CustomerLedgerEntry::where('entry_type', 'udhaar')->count());
    }
}
