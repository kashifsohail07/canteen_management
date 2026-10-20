<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_reservations', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('rent_obligation_id')
                ->constrained('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rent_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
