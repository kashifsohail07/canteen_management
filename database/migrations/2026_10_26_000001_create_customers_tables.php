<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('current_balance', 14, 2)->default(0); // positive = customer owes canteen
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('opening_balance_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('last_used_at');
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('daily_session_id')->nullable()->constrained('daily_sessions')->nullOnDelete();
            $table->string('entry_type'); // opening_balance, udhaar, payment, adjustment
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 14, 2)->default(0);  // increases receivable (udhaar)
            $table->decimal('credit', 14, 2)->default(0); // decreases receivable (payment)
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['customer_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
        Schema::dropIfExists('customers');
    }
};
