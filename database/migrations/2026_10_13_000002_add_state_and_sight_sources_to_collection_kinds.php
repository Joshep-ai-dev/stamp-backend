<?php

use App\Services\UsStates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_kind_states', function (Blueprint $table): void {
            $table->string('collection_kind_id');
            $table->foreignId('country_state_id')->constrained('country_states')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->primary(['collection_kind_id', 'country_state_id']);
            $table->foreign('collection_kind_id')->references('id')->on('collectionkind')->cascadeOnDelete();
        });

        Schema::create('collection_kind_sights', function (Blueprint $table): void {
            $table->string('collection_kind_id');
            $table->foreignId('sight_id')->constrained('sights')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->primary(['collection_kind_id', 'sight_id']);
            $table->foreign('collection_kind_id')->references('id')->on('collectionkind')->cascadeOnDelete();
        });

        if (DB::table('collectionkind')->where('id', 'usa')->exists()) {
            foreach (UsStates::NAMES as $name) {
                $normalized = UsStates::normalize($name);
                $stateId = DB::table('country_states')
                    ->where('country_code', 'US')
                    ->where('normalized_name', $normalized)
                    ->value('id');

                if (! $stateId) {
                    $stateId = DB::table('country_states')->insertGetId([
                        'country_code' => 'US',
                        'name' => $name,
                        'normalized_name' => $normalized,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('collection_kind_states')->insertOrIgnore([
                    'collection_kind_id' => 'usa',
                    'country_state_id' => $stateId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_kind_sights');
        Schema::dropIfExists('collection_kind_states');
    }
};
