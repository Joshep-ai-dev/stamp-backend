<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_destinations', function (Blueprint $table): void {
            $table->unsignedInteger('lesson_number')->nullable()->unique();
            $table->json('questions')->nullable();
        });

        DB::table('daily_destinations')->orderBy('display_order')->orderBy('name')->get()
            ->each(function ($lesson, int $index): void {
                DB::table('daily_destinations')->where('id', $lesson->id)->update([
                    'lesson_number' => $index + 1,
                    'questions' => json_encode([[
                        'prompt' => $lesson->question,
                        'answers' => json_decode($lesson->options, true) ?: [],
                        'correctAnswer' => (int) $lesson->correct_answer,
                        'explanation' => $lesson->content,
                    ]]),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('daily_destinations', fn (Blueprint $table) => $table->dropColumn(['lesson_number', 'questions']));
    }
};
