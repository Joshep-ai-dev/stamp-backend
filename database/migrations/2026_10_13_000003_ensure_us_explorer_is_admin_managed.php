<?php

use App\Services\UsStates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $kind = DB::table('collectionkind')->where('id', 'usa')->first();

        if (! $kind) {
            $kind = DB::table('collectionkind')
                ->whereRaw('LOWER(title) = ?', ['united states explorer'])
                ->first();
        }

        if (! $kind) {
            DB::table('collectionkind')->insert([
                'id' => 'usa',
                'image' => null,
                'title' => 'United States Explorer',
                'detail' => 'Visit all 50 states',
                'country_code' => null,
                'access' => 'free',
                'is_published' => true,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kind = DB::table('collectionkind')->where('id', 'usa')->first();
        }

        foreach (UsStates::NAMES as $name) {
            $stateId = DB::table('country_states')
                ->where('country_code', 'US')
                ->where('normalized_name', UsStates::normalize($name))
                ->value('id');

            if ($stateId) {
                DB::table('collection_kind_states')->insertOrIgnore([
                    'collection_kind_id' => $kind->id,
                    'country_state_id' => $stateId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep the admin-managed collection and its content when rolling back.
    }
};
