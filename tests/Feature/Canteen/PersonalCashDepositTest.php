<?php

namespace Tests\Feature\Canteen;

use App\Models\CashTransaction;
use App\Models\OwnerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsCanteenBasics;
use Tests\TestCase;

class PersonalCashDepositTest extends TestCase
{
    use RefreshDatabase, SeedsCanteenBasics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanteenBasics();
    }

    public function test_owner_deposit_increases_canteen_cash_and_owner_recoverable(): void
    {
        $this->fundCanteenCash(1000);

        $this->actingAs($this->admin)->post('/owner/deposit', [
            'amount' => '5,000',
            'notes' => 'Float for the week',
        ])->assertRedirect(route('dashboard'));

        $this->assertMoney(6000, $this->canteenCash());
        $this->assertMoney(5000, $this->ownerRecoverable());

        $tx = OwnerTransaction::sole();
        $this->assertSame('cash_deposit', $tx->type);
        $this->assertSame('increase_recoverable', $tx->direction);
        $this->assertMoney(5000, $tx->balance_after);

        // both cash accounts moved: canteen cash and owner recoverable
        $this->assertSame(1, CashTransaction::where('transaction_type', 'owner_deposit')->where('direction', 'in')
            ->whereHas('cashAccount', fn ($q) => $q->where('code', 'canteen_cash'))->count());
    }

    public function test_deposits_accumulate(): void
    {
        $this->actingAs($this->admin)->post('/owner/deposit', ['amount' => '1000'])->assertRedirect();
        $this->actingAs($this->admin)->post('/owner/deposit', ['amount' => '2500'])->assertRedirect();

        $this->assertMoney(3500, $this->canteenCash());
        $this->assertMoney(3500, $this->ownerRecoverable());
    }

    public function test_deposit_must_be_positive(): void
    {
        $this->actingAs($this->admin)->post('/owner/deposit', ['amount' => '0'])->assertSessionHasErrors('amount');
        $this->actingAs($this->admin)->post('/owner/deposit', ['amount' => '-5'])->assertSessionHasErrors('amount');

        $this->assertMoney(0, $this->canteenCash());
        $this->assertSame(0, OwnerTransaction::count());
    }

    public function test_owner_ledger_page_lists_the_deposit(): void
    {
        $this->actingAs($this->admin)->post('/owner/deposit', ['amount' => '1234'])->assertRedirect();

        $this->actingAs($this->admin)->get('/owner')->assertOk()->assertSee('1,234');
    }
}
