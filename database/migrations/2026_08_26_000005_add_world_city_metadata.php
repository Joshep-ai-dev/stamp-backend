<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->string('ascii_name', 150)->nullable()->after('name');
            $table->string('iso3', 3)->nullable()->after('country_code');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('population')->nullable()->index();
            $table->string('capital', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropColumn(['ascii_name', 'iso3', 'latitude', 'longitude', 'population', 'capital']);
        });
    }
};
