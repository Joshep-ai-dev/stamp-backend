<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sights') || ! in_array(Schema::getColumnType('sights', 'id'), ['string', 'varchar'], true)) {
            return;
        }
        Schema::create('sights_auto', fn (Blueprint $table) => $this->columns($table, true));
        $mapping = [];
        foreach (DB::table('sights')->orderBy('created_at')->orderBy('id')->get() as $sight) {
            $oldId = (string) $sight->id;
            $values = (array) $sight;
            unset($values['id'], $values['latitude'], $values['longitude']);
            $mapping[$oldId] = DB::table('sights_auto')->insertGetId($values);
        }
        foreach ($mapping as $oldId => $newId) {
            DB::table('completions')->where('sight_id', $oldId)->update(['sight_id' => (string) $newId]);
            DB::table('wishlists')->where('target_id', $oldId)->update(['target_id' => (string) $newId]);
        }
        Schema::drop('sights');
        Schema::rename('sights_auto', 'sights');
    }

    public function down(): void
    {
        if (! Schema::hasTable('sights') || in_array(Schema::getColumnType('sights', 'id'), ['string', 'varchar'], true)) {
            return;
        }
        Schema::create('sights_string', fn (Blueprint $table) => $this->columns($table, false));
        foreach (DB::table('sights')->get() as $sight) {
            DB::table('sights_string')->insert((array) $sight);
        }
        Schema::drop('sights');
        Schema::rename('sights_string', 'sights');
    }

    private function columns(Blueprint $table, bool $incrementing): void
    {
        $incrementing ? $table->id() : $table->string('id')->primary();
        $table->char('country_code', 2)->index();
        $table->foreignId('city_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('slug')->index();
        $table->text('description')->nullable();
        $table->string('category')->default('attraction');
        $table->text('image_url')->nullable();
        $table->boolean('is_featured')->default(true);
        $table->boolean('is_premium')->default(false);
        $table->unsignedInteger('display_order')->default(0);
        $table->timestamps();
    }
};
