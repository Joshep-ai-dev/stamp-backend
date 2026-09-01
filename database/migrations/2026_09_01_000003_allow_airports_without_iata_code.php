<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('airports') || ! Schema::hasColumn('airports', 'iata_code')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE airports ALTER COLUMN iata_code DROP NOT NULL');
            DB::statement("UPDATE airports SET iata_code = NULL WHERE BTRIM(iata_code) = ''");

            return;
        }

        Schema::table('airports', function ($table): void {
            $table->string('iata_code', 8)->nullable()->change();
        });

        DB::table('airports')->where('iata_code', '')->update(['iata_code' => null]);
    }

    public function down(): void
    {
        // Airports without an IATA code cannot be represented by the legacy
        // NOT NULL + UNIQUE schema without manufacturing invalid codes.
    }
};
