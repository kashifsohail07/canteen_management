<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('opening_balance_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('last_used_at');
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('default_rate', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sale_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // canteen_cash, rent_reserve, owner_recoverable
            $table->string('name');
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('daily_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->string('day_type')->default('normal'); // normal, weekend, holiday, ptm, special
            $table->string('status')->default('open'); // open, closed
            $table->decimal('opening_canteen_cash', 14, 2)->default(0);
            $table->decimal('opening_rent_reserve', 14, 2)->default(0);
            $table->decimal('opening_owner_recoverable', 14, 2)->default(0);
            $table->decimal('expected_closing_cash', 14, 2)->nullable();
            $table->decimal('actual_counted_cash', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->text('difference_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('business_date');
            $table->index('status');
        });

        Schema::create('school_calendar', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('day_type');
            $table->string('label')->nullable();
            $table->boolean('is_working_day')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->foreignId('sale_segment_id')->constrained('sale_segments');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('daily_session_id');
            $table->index('recorded_at');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('entry_mode')->default('total'); // total, itemized
            $table->decimal('bill_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('payment_source')->nullable(); // canteen_cash, personal_pocket
            $table->decimal('previous_balance', 14, 2)->default(0);
            $table->decimal('new_balance', 14, 2)->default(0);
            $table->string('bill_image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('daily_session_id');
            $table->index('vendor_id');
            $table->index('recorded_at');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('rate', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_source'); // canteen_cash, personal_pocket
            $table->decimal('previous_balance', 14, 2)->default(0);
            $table->decimal('new_balance', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('daily_session_id');
            $table->index('vendor_id');
        });

        Schema::create('vendor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('daily_session_id')->nullable()->constrained('daily_sessions')->nullOnDelete();
            $table->string('entry_type'); // opening_balance, purchase, payment, adjustment, reversal
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);  // increases amount owed
            $table->decimal('credit', 14, 2)->default(0); // decreases amount owed
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['vendor_id', 'recorded_at']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained('cash_accounts');
            $table->foreignId('daily_session_id')->nullable()->constrained('daily_sessions')->nullOnDelete();
            $table->string('direction'); // in, out
            $table->decimal('amount', 14, 2);
            $table->string('transaction_type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();

            $table->index(['cash_account_id', 'recorded_at']);
        });

        Schema::create('owner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->nullable()->constrained('daily_sessions')->nullOnDelete();
            $table->string('type'); // vendor_payment, cash_deposit, repayment
            $table->decimal('amount', 14, 2);
            $table->foreignId('vendor_payment_id')->nullable()->constrained('vendor_payments')->nullOnDelete();
            $table->string('direction'); // increase_recoverable, decrease_recoverable
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();
        });

        Schema::create('rent_obligations', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('target_amount', 14, 2)->default(71000);
            $table->decimal('reserved_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('rent_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->foreignId('rent_obligation_id')->constrained('rent_obligations');
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();
        });

        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_obligation_id')->constrained('rent_obligations');
            $table->foreignId('daily_session_id')->nullable()->constrained('daily_sessions')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_source'); // rent_reserve, personal_pocket, canteen_cash
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_source')->default('canteen_cash');
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('created_by')->constrained('users');
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_session_id')->constrained('daily_sessions');
            $table->string('activity_type');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('secondary_amount', 14, 2)->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('color')->default('blue'); // green, red, orange, blue
            $table->boolean('has_image')->default(false);
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['daily_session_id', 'recorded_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('route');
            $table->integer('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'idempotency_keys', 'audit_logs', 'activity_logs', 'expenses', 'expense_categories',
            'rent_payments', 'rent_reservations', 'rent_obligations', 'owner_transactions',
            'cash_transactions', 'vendor_ledger_entries', 'vendor_payments', 'purchase_items',
            'purchases', 'sales', 'school_calendar', 'daily_sessions', 'cash_accounts',
            'sale_segments', 'items', 'vendors', 'settings',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
