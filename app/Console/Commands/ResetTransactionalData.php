<?php

namespace App\Console\Commands;

use App\Models\CashAccount;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipe day-to-day hisab data for a fresh start.
 * Keeps: users, vendors (names/phones/rates), sale segments, settings, expense categories, items.
 */
class ResetTransactionalData extends Command
{
    protected $signature = 'canteen:fresh-data
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear all sales/mall/payments/cash history. Keeps users and vendors.';

    /** @var list<string> */
    private array $wipe = [
        'purchase_items',
        'purchases',
        'vendor_payments',
        'vendor_ledger_entries',
        'sales',
        'cash_transactions',
        'owner_transactions',
        'rent_payments',
        'rent_reservations',
        'rent_obligations',
        'expenses',
        'activity_logs',
        'audit_logs',
        'idempotency_keys',
        'daily_sessions',
        'school_calendar',
        'sessions',
        'password_reset_tokens',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will DELETE all sales, mall, payments, cash, rent and activity.');
            $this->info('Kept: users, vendors, segments, settings, expense categories.');
            if (! $this->confirm('Continue and wipe transactional data?', false)) {
                $this->line('Cancelled.');

                return self::SUCCESS;
            }
        }

        $existing = array_values(array_filter(
            $this->wipe,
            fn (string $table) => Schema::hasTable($table),
        ));

        // Truncate is not always transaction-safe on MySQL — wipe then reset balances.
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($existing as $table) {
                DB::table($table)->delete();
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                }
                $this->line("  cleared {$table}");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if (Schema::hasTable('cash_accounts')) {
            CashAccount::query()->update(['balance' => '0.00']);
            $this->line('  reset cash_accounts balances → 0');
        }

        if (Schema::hasTable('vendors')) {
            // Keep vendor rows; zero balances for a true fresh start.
            Vendor::withTrashed()->update([
                'current_balance' => '0.00',
                'opening_balance' => '0.00',
                'opening_balance_locked' => false,
                'last_used_at' => null,
            ]);
            $this->line('  reset vendor balances → 0 (vendors kept)');
        }

        $this->clearBillPhotos();

        $users = Schema::hasTable('users') ? DB::table('users')->count() : 0;
        $vendors = Schema::hasTable('vendors') ? DB::table('vendors')->count() : 0;

        $this->newLine();
        $this->info("Done. Fresh system ready. Users kept: {$users}. Vendors kept: {$vendors}.");
        $this->comment('Tip: set vendor opening balances again if needed from Vendors.');

        return self::SUCCESS;
    }

    private function clearBillPhotos(): void
    {
        try {
            $disk = Storage::disk('local');
            foreach (['invoices', 'bills', 'purchases'] as $dir) {
                if ($disk->exists($dir)) {
                    $disk->deleteDirectory($dir);
                    $this->line("  deleted storage photos: {$dir}/");
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  could not clear bill photos: '.$e->getMessage());
        }
    }
}
