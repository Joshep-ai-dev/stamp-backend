<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('city_name', 150);
            $table->string('country', 100);
            $table->char('country_code', 2)->index();
            $table->char('continent_code', 2)->index();
            $table->string('subcountry', 150)->nullable();
            $table->date('visited_at')->index();
            $table->string('note', 140)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
