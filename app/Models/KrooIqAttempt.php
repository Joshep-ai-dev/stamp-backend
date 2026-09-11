<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrooIqAttempt extends Model
{
    protected $fillable = ['user_id', 'quiz_date', 'question_ids', 'answers', 'correct_count', 'score_before', 'score_after', 'completed_at'];

    protected function casts(): array
    {
        return ['quiz_date' => 'date:Y-m-d', 'question_ids' => 'array', 'answers' => 'array', 'score_before' => 'decimal:2', 'score_after' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
