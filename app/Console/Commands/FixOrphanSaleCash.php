<?php

namespace App\Console\Commands;

use App\Enums\CashAccountCode;
use App\Models\ActivityLog;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\DailySession;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashLedgerService;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair cash / activity left behind when a sale row was deleted outside the app
 * (e.g. direct SQL DELETE from sales).
 *
 * Dry-run by default. Pass --apply to post reversals.
 */
class FixOrphanSaleCash extends Command
{
    protected $signature = 'canteen:fix-orphan-sale-cash
                            {--date= : Limit to business date Y-m-d (e.g. 2026-10-26)}
                            {--apply : Actually reverse cash + remove orphan activity rows}
                            {--user= : User id for the reversal audit (defaults to first super_user/admin)}';

    protected $description = 'Reverse canteen cash still credited after a sale row was deleted from the DB';

    public function handle(CashLedgerService $cash): int
    {
        $date = $this->option('date');
        $apply = (bool) $this->option('apply');

        $canteen = CashAccount::where('code', CashAccountCode::CanteenCash->value)->first();
        if (! $canteen) {
            $this->error('canteen_cash account not found.');

            return self::FAILURE;
        }

        $query = CashTransaction::query()
            ->where('cash_account_id', $canteen->id)
            ->where('reference_type', 'sale')
            ->where('transaction_type', 'sale')
            ->where('direction', 'in')
            ->where('is_reversed', false)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('sales')
                    ->whereColumn('sales.id', 'cash_transactions.reference_id');
            });

        if ($date) {
            $sessionIds = DailySession::whereDate('business_date', $date)->pluck('id');
            if ($sessionIds->isEmpty()) {
                $this->warn("No daily session for {$date}.");

                return self::SUCCESS;
            }
            $query->whereIn('daily_session_id', $sessionIds);
        }

        $orphans = $query->orderBy('id')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphan sale cash credits found'.($date ? " for {$date}" : '').'.');
            $this->line('Current canteen cash: '.Money::format($cash->getBalances()['canteen_cash']));

            return self::SUCCESS;
        }

        $this->table(
            ['cash_tx', 'sale_id', 'session', 'amount', 'notes', 'recorded_at', 'already_reversed?'],
            $orphans->map(function (CashTransaction $tx) {
                $reversed = CashTransaction::query()
                    ->where('reference_type', 'sale')
                    ->where('reference_id', $tx->reference_id)
                    ->where('transaction_type', 'reversal')
                    ->where('direction', 'out')
                    ->where('is_reversed', false)
                    ->where('amount', $tx->amount)
                    ->exists();

                return [
                    $tx->id,
                    $tx->reference_id,
                    $tx->daily_session_id,
                    Money::format($tx->amount),
                    $tx->notes,
                    optional($tx->recorded_at)->toDateTimeString(),
                    $reversed ? 'yes' : 'no',
                ];
            })->all(),
        );

        $toFix = $orphans->filter(function (CashTransaction $tx) {
            return ! CashTransaction::query()
                ->where('reference_type', 'sale')
                ->where('reference_id', $tx->reference_id)
                ->where('transaction_type', 'reversal')
                ->where('direction', 'out')
                ->where('is_reversed', false)
                ->where('amount', $tx->amount)
                ->exists();
        });

        $total = Money::zero();
        foreach ($toFix as $tx) {
            $total = Money::add($total, $tx->amount);
        }

        $before = $cash->getBalances()['canteen_cash'];
        $this->line('Orphans needing fix: '.$toFix->count().' · total '.Money::format($total));
        $this->line('Canteen cash now: '.Money::format($before));
        if (Money::isPositive($total)) {
            $this->line('After fix would be: '.Money::format(Money::sub($before, $total)));
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Dry run only. Re-run with --apply to reverse cash and clear activity.');

            return self::SUCCESS;
        }

        if ($toFix->isEmpty()) {
            $this->info('Nothing to apply (already reversed). Cleaning orphan activity only…');
            $this->clearOrphanActivity($orphans->pluck('reference_id')->all());

            return self::SUCCESS;
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('No user found for --user / admin. Pass --user=ID.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($toFix, $cash, $user) {
            foreach ($toFix as $tx) {
                $session = DailySession::find($tx->daily_session_id);
                $cash->debit(CashAccountCode::CanteenCash, $tx->amount, 'reversal', [
                    'session' => $session,
                    'reference_type' => 'sale',
                    'reference_id' => $tx->reference_id,
                    'notes' => 'Orphan sale removed from DB: cash was still in (sale #'.$tx->reference_id.')',
                    'user' => $user,
                ]);
                $this->line("Reversed cash_tx #{$tx->id} sale #{$tx->reference_id} ".Money::format($tx->amount));
            }

            $this->clearOrphanActivity($toFix->pluck('reference_id')->all());
        });

        $after = $cash->getBalances()['canteen_cash'];
        $this->newLine();
        $this->info('Done. Canteen cash: '.Money::format($before).' → '.Money::format($after));

        return self::SUCCESS;
    }

    /** @param  list<int|string|null>  $saleIds */
    private function clearOrphanActivity(array $saleIds): void
    {
        $ids = array_values(array_filter(array_map('intval', $saleIds)));
        if ($ids === []) {
            return;
        }

        $deleted = ActivityLog::query()
            ->whereIn('reference_id', $ids)
            ->where(function ($q) {
                $q->where('reference_type', 'sale')
                    ->orWhere('reference_type', Sale::class)
                    ->orWhere('activity_type', 'sale');
            })
            ->delete();

        $this->line("Removed {$deleted} orphan activity row(s).");
    }

    private function resolveUser(): ?User
    {
        $id = $this->option('user');
        if ($id) {
            return User::find((int) $id);
        }

        return User::query()
            ->whereIn('role', ['super_user', 'admin'])
            ->orderByRaw("FIELD(role, 'super_user', 'admin')")
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
