<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airports', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_id')->primary();
            $table->string('name', 255);
            $table->char('country_code', 2);
            $table->string('municipality', 180);
            $table->string('normalized_municipality', 180);
            $table->char('iata_code', 3)->unique();
            $table->string('icao_code', 8)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->index(['country_code', 'normalized_municipality']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
