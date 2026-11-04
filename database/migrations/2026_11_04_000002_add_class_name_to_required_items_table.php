<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('required_items', function (Blueprint $table) {
            $table->string('class_name', 60)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('required_items', function (Blueprint $table) {
            $table->dropColumn('class_name');
        });
    }
};
