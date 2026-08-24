<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectionkind', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->text('image')->nullable();
            $table->string('title');
            $table->text('detail')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
        Schema::create('collectionlist', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('collectionkind_id');
            $table->text('image')->nullable();
            $table->string('title');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('detail')->nullable();
            $table->string('access', 16)->default('free');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->foreign('collectionkind_id')->references('id')->on('collectionkind')->cascadeOnDelete();
        });

        if (Schema::hasTable('managed_collections')) {
            foreach (DB::table('managed_collections')->orderBy('display_order')->get() as $kind) {
                DB::table('collectionkind')->insert(['id' => $kind->id, 'image' => $kind->image_url, 'title' => $kind->title, 'detail' => $kind->detail ?: $kind->description, 'is_published' => $kind->is_published, 'display_order' => $kind->display_order, 'created_at' => $kind->created_at, 'updated_at' => $kind->updated_at]);
                foreach (json_decode($kind->places ?: '[]', true) as $order => $item) {
                    $cityId = isset($item['cityId']) ? DB::table('cities')->where('geoname_id', $item['cityId'])->value('id') : null;
                    DB::table('collectionlist')->insert(['id' => $item['id'] ?? (string) Str::uuid(), 'collectionkind_id' => $kind->id, 'image' => $item['imageUrl'] ?? $item['image'] ?? null, 'title' => $item['name'] ?? $item['title'] ?? 'Location', 'city_id' => $cityId, 'location' => collect([$item['city'] ?? null, $item['country'] ?? null])->filter()->join(', '), 'detail' => $item['content'] ?? $item['detail'] ?? null, 'access' => ($item['isPremium'] ?? false) ? 'pro' : 'free', 'display_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            Schema::drop('managed_collections');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collectionlist');
        Schema::dropIfExists('collectionkind');
    }
};
