<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $extension = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm') AS installed");
        if (! in_array($extension?->installed, [true, 1, '1', 't', 'true'], true)) {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS cities_normalized_subcountry_trgm_idx ON cities USING gin (normalized_subcountry gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS countries_normalized_name_trgm_idx ON countries USING gin (normalized_name gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS cities_normalized_subcountry_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS countries_normalized_name_trgm_idx');
    }
};
