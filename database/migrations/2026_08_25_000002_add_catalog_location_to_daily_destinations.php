<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_destinations', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->after('country')->index();
            $table->foreignId('city_id')->nullable()->after('city')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_destinations', function (Blueprint $table): void {
            $table->dropForeign(['city_id']);
            $table->dropColumn(['country_code', 'city_id']);
        });
    }
};
