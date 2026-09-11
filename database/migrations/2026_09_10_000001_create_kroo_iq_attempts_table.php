<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('kroo_iq_score', 6, 2)->default(0);
        });

        Schema::create('kroo_iq_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('quiz_date');
            $table->json('question_ids');
            $table->json('answers')->default('[]');
            $table->unsignedTinyInteger('correct_count')->default(0);
            $table->decimal('score_before', 6, 2);
            $table->decimal('score_after', 6, 2);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'quiz_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kroo_iq_attempts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('kroo_iq_score'));
    }
};
