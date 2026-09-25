<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('kroo_iq_score', 6, 2)->default(0)->change();
        });

        DB::table('users')->update(['kroo_iq_score' => 0]);

        DB::table('kroo_iq_attempts')
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->get(['user_id', 'question_ids', 'correct_count'])
            ->groupBy('user_id')
            ->each(function ($attempts, string $userId): void {
                $score = $attempts->sum(function ($attempt): float {
                    $questionIds = json_decode($attempt->question_ids, true) ?: [];
                    $lessonId = explode(':', $questionIds[0] ?? '', 2)[0];
                    $lessonNumber = $lessonId === ''
                        ? null
                        : DB::table('daily_destinations')->where('id', $lessonId)->value('lesson_number');

                    return (int) $attempt->correct_count * 0.05;
                });

                DB::table('users')->where('id', $userId)->update([
                    'kroo_iq_score' => round($score, 2),
                ]);
            });
    }

    public function down(): void
    {
        // The previous non-zero baseline was invalid and must not be restored.
    }
};
