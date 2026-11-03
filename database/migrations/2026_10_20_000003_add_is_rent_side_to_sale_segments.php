<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_segments', function (Blueprint $table) {
            $table->boolean('is_rent_side')->default(false)->after('is_active');
        });

        $max = (int) DB::table('sale_segments')->max('sort_order');

        $exists = DB::table('sale_segments')
            ->where('is_rent_side', true)
            ->orWhere('slug', 'rent-side')
            ->orWhereRaw('LOWER(name) = ?', ['rent side'])
            ->orWhereRaw('LOWER(name) = ?', ['rent'])
            ->exists();

        if (! $exists) {
            DB::table('sale_segments')->insert([
                'name' => 'Rent Side',
                'slug' => 'rent-side',
                'sort_order' => $max + 1,
                'is_active' => true,
                'is_rent_side' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sale_segments')
                ->where(function ($q) {
                    $q->where('slug', 'rent-side')
                        ->orWhereRaw('LOWER(name) = ?', ['rent side'])
                        ->orWhereRaw('LOWER(name) = ?', ['rent']);
                })
                ->update(['is_rent_side' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('sale_segments', function (Blueprint $table) {
            $table->dropColumn('is_rent_side');
        });
    }
};
