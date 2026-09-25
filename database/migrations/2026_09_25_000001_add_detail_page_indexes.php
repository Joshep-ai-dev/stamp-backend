<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->index(['country_code', 'name'], 'cities_country_name_idx');
            $table->index(['country_code', 'subcountry', 'name'], 'cities_country_state_name_idx');
        });

        Schema::table('sights', function (Blueprint $table): void {
            $table->index(['country_code', 'is_featured', 'name'], 'sights_country_featured_name_idx');
            $table->index(['city_id', 'is_featured', 'name'], 'sights_city_featured_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropIndex('cities_country_name_idx');
            $table->dropIndex('cities_country_state_name_idx');
        });

        Schema::table('sights', function (Blueprint $table): void {
            $table->dropIndex('sights_country_featured_name_idx');
            $table->dropIndex('sights_city_featured_name_idx');
        });
    }
};
