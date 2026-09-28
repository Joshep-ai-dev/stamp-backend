<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', fn (Blueprint $table) => $table->text('description')->nullable());
        Schema::table('country_states', fn (Blueprint $table) => $table->text('description')->nullable());
        Schema::table('cities', fn (Blueprint $table) => $table->text('description')->nullable());

        Schema::create('ai_content_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 20);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();
        });
        Schema::create('ai_content_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_content_batches')->cascadeOnDelete();
            $table->string('target_id', 40);
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'target_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_items');
        Schema::dropIfExists('ai_content_batches');
        Schema::table('cities', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('country_states', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
