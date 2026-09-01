<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('airports')) {
            return;
        }

        $columns = [
            'source_id' => fn (Blueprint $table) => $table->string('source_id', 191)->nullable(),
            'icao_code' => fn (Blueprint $table) => $table->string('icao_code', 8)->nullable(),
            'iata_code' => fn (Blueprint $table) => $table->string('iata_code', 8)->nullable(),
            'name' => fn (Blueprint $table) => $table->string('name')->nullable(),
            'normalized_city' => fn (Blueprint $table) => $table->string('normalized_city')->nullable(),
            'city' => fn (Blueprint $table) => $table->string('city')->nullable(),
            'normalized_state' => fn (Blueprint $table) => $table->string('normalized_state')->nullable(),
            'state' => fn (Blueprint $table) => $table->string('state')->nullable(),
            'country_code' => fn (Blueprint $table) => $table->string('country_code', 2)->nullable(),
            'latitude' => fn (Blueprint $table) => $table->decimal('latitude', 10, 7)->nullable(),
            'longitude' => fn (Blueprint $table) => $table->decimal('longitude', 11, 7)->nullable(),
            'elevation' => fn (Blueprint $table) => $table->integer('elevation')->nullable(),
            'timezone' => fn (Blueprint $table) => $table->string('timezone')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $name => $addColumn) {
            if (! Schema::hasColumn('airports', $name)) {
                Schema::table('airports', $addColumn);
            }
        }

        $hasUniqueIcao = collect(Schema::getIndexes('airports'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['icao_code'],
        );
        if (! $hasUniqueIcao) {
            Schema::table('airports', fn (Blueprint $table) => $table->unique('icao_code'));
        }
    }

    public function down(): void
    {
        // Compatibility migrations intentionally preserve existing schemas.
    }
};
