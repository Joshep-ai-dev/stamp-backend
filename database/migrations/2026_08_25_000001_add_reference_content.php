<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('friend_code', 64)->nullable()->unique());

        Schema::create('sights', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('is_featured')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('daily_destinations', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('country');
            $table->string('city')->nullable();
            $table->text('image_url')->nullable();
            $table->string('icon', 16)->default('🌍');
            $table->text('content');
            $table->text('question');
            $table->json('options');
            $table->unsignedSmallInteger('correct_answer')->default(0);
            $table->date('publish_date')->nullable()->index();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('friends', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('friend_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'friend_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friends');
        Schema::dropIfExists('daily_destinations');
        Schema::dropIfExists('sights');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('friend_code'));
    }
};
