<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some installations already have an airport table from the earlier
        // lookup implementation. The following compatibility migration adds
        // the catalog columns without destroying those records.
        if (Schema::hasTable('airports')) {
            return;
        }

        Schema::create('airports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('icao_code', 8)->unique();
            $table->string('iata_code', 8)->nullable()->unique();
            $table->string('name');
            $table->string('municipality')->default('');
            $table->string('normalized_municipality')->default('')->index();
            $table->string('normalized_city')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('normalized_state')->nullable()->index();
            $table->string('state')->nullable();
            $table->string('country_code', 2)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->integer('elevation')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
            $table->index(['country_code', 'normalized_city']);
            $table->index(['country_code', 'normalized_state']);
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
