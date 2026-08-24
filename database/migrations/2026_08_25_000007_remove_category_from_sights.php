<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sights', 'category')) {
            Schema::table('sights', fn (Blueprint $table) => $table->dropColumn('category'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sights', 'category')) {
            Schema::table('sights', fn (Blueprint $table) => $table->string('category')->default('attraction'));
        }
    }
};
