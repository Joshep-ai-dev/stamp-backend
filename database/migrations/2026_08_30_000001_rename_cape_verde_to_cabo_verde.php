<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('countries')->where('code', 'CV')->update([
            'name' => 'Cabo Verde',
            'normalized_name' => 'cabo verde',
            'updated_at' => now(),
        ]);
        DB::table('visits')->where('country_code', 'CV')->update(['country' => 'Cabo Verde']);
        DB::table('daily_destinations')->where('country_code', 'CV')->update(['country' => 'Cabo Verde']);
    }

    public function down(): void
    {
        DB::table('countries')->where('code', 'CV')->update([
            'name' => 'Cape Verde',
            'normalized_name' => 'cape verde',
            'updated_at' => now(),
        ]);
        DB::table('visits')->where('country_code', 'CV')->update(['country' => 'Cape Verde']);
        DB::table('daily_destinations')->where('country_code', 'CV')->update(['country' => 'Cape Verde']);
    }
};
