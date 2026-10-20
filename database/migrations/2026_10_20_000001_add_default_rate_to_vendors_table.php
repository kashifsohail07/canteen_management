<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('default_item_name')->nullable()->after('notes');
            $table->string('default_unit', 20)->nullable()->after('default_item_name');
            $table->decimal('default_rate', 14, 2)->nullable()->after('default_unit');
        });

        // Samosa / Samosay vendors: 1 piece = Rs. 20 (fixed).
        DB::table('vendors')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%samosa%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%samosay%']);
            })
            ->update([
                'default_item_name' => 'Samosay',
                'default_unit' => 'pcs',
                'default_rate' => '20.00',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['default_item_name', 'default_unit', 'default_rate']);
        });
    }
};
