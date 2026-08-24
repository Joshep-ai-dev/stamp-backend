<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        }

        Schema::create('countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->string('name', 100)->index();
            $table->string('normalized_name', 100)->index();
            $table->char('continent_code', 2)->index();
            $table->string('flag', 8)->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('geoname_id', 32)->unique();
            $table->string('name', 150);
            $table->string('normalized_name', 150)->index();
            $table->char('country_code', 2);
            $table->string('subcountry', 150)->nullable();
            $table->string('normalized_subcountry', 150)->nullable()->index();
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries');
            $table->index(['country_code', 'normalized_name']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX cities_normalized_name_trgm_idx ON cities USING gin (normalized_name gin_trgm_ops)');
        }

        Schema::create('catalog_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset', 100)->index();
            $table->string('version', 50);
            $table->char('checksum', 64)->unique();
            $table->unsignedInteger('row_count');
            $table->timestampTz('imported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_versions');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
