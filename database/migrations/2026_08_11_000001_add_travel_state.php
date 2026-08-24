<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('plan', 10)->default('free');
            $table->string('nationality', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('photo_uri')->nullable();
        });

        Schema::table('visits', function (Blueprint $table): void {
            $table->json('places')->nullable();
        });

        Schema::create('completions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('sight_id');
            $table->timestampTz('completed_at');
            $table->unique(['user_id', 'sight_id']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_id');
            $table->timestampTz('saved_at');
            $table->unique(['user_id', 'target_id']);
        });

        Schema::create('rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('kroo_points', 8, 3)->default(0);
            $table->boolean('unlocked')->default(false);
            $table->timestamps();
        });

        Schema::create('collection_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('collection_id');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_progress');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('completions');
        Schema::table('visits', fn (Blueprint $table) => $table->dropColumn('places'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['plan', 'nationality', 'date_of_birth', 'photo_uri']));
    }
};
