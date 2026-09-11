<?php

namespace App\Http\Controllers;

use App\Models\DailyDestination;
use App\Models\KrooIqAttempt;
use App\Services\ImageUrl;
use App\Services\KrooIqAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KrooIqController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $this->authorizeMember($request);
        $date = now()->toDateString();
        $lesson = $this->lessonFor($date);
        abort_if(! $lesson, 404, 'Today\'s Kroo IQ quiz is not available yet.');
        $questions = $this->questions($lesson);
        abort_if($questions->isEmpty(), 404, 'This Kroo IQ lesson does not have questions yet.');

        $attempt = KrooIqAttempt::firstOrCreate(
            ['user_id' => $request->user()->id, 'quiz_date' => $date],
            [
                'question_ids' => $questions->pluck('id')->all(),
                'answers' => [],
                'score_before' => $request->user()->kroo_iq_score,
                'score_after' => $request->user()->kroo_iq_score,
            ],
        );

        $lessonId = explode(':', $attempt->question_ids[0] ?? $lesson->id, 2)[0];
        $lesson = DailyDestination::findOrFail($lessonId);

        return response()->json($this->payload($attempt, $lesson, $this->questions($lesson)));
    }

    public function answer(Request $request): JsonResponse
    {
        $this->authorizeMember($request);
        $data = $request->validate([
            'questionId' => ['required', 'string', 'max:255'],
            'selectedAnswer' => ['required', 'integer', 'min:0'],
        ]);

        $payload = DB::transaction(function () use ($request, $data): array {
            $attempt = KrooIqAttempt::where('user_id', $request->user()->id)
                ->where('quiz_date', now()->toDateString())->lockForUpdate()->firstOrFail();
            abort_if($attempt->completed_at, 409, 'Today\'s quiz is already complete.');

            $position = array_search($data['questionId'], $attempt->question_ids, true);
            abort_if($position === false, 422, 'This question is not part of today\'s quiz.');
            $answers = $attempt->answers ?? [];
            abort_if(collect($answers)->contains(fn ($answer) => $answer['questionId'] === $data['questionId']), 409, 'This question has already been answered.');
            abort_unless(count($answers) === $position, 409, 'Answer the questions in order.');

            [$lessonId, $questionIndex] = array_pad(explode(':', $data['questionId'], 2), 2, null);
            $lesson = DailyDestination::findOrFail($lessonId);
            $question = $this->questions($lesson)->get((int) $questionIndex);
            abort_if(! $question, 422, 'The selected question is invalid.');
            abort_if($data['selectedAnswer'] >= count($question['answers']), 422, 'The selected answer is invalid.');
            $isCorrect = $data['selectedAnswer'] === $question['correctAnswer'];
            $answers[] = ['questionId' => $data['questionId'], 'selectedAnswer' => $data['selectedAnswer'], 'correct' => $isCorrect];
            $attempt->answers = $answers;
            $attempt->correct_count = collect($answers)->where('correct', true)->count();
            $attempt->score_after = round((float) $attempt->score_before + ($attempt->correct_count * 0.05), 2);

            if (count($answers) === count($attempt->question_ids)) {
                $attempt->completed_at = now();
                $request->user()->forceFill(['kroo_iq_score' => $attempt->score_after])->save();
            }
            $attempt->save();

            return [
                'correct' => $isCorrect,
                'correctAnswer' => $question['correctAnswer'],
                'explanation' => $question['explanation'],
                'attempt' => $this->attemptData($attempt),
            ];
        });

        return response()->json($payload);
    }

    private function lessonFor(string $date): ?DailyDestination
    {
        $lessons = DailyDestination::where('is_published', true)
            ->where(fn ($query) => $query->whereDate('publish_date', '<=', $date)->orWhereNull('publish_date'))
            ->orderBy('lesson_number')->orderBy('created_at')->get();
        if ($lessons->isEmpty()) return null;

        return $lessons[(now()->dayOfYear - 1) % $lessons->count()];
    }

    private function questions(DailyDestination $lesson)
    {
        $questions = $lesson->questions ?: [[
            'prompt' => $lesson->question,
            'answers' => $lesson->options ?? [],
            'correctAnswer' => $lesson->correct_answer,
            'explanation' => $lesson->content,
        ]];
        return collect($questions)->take(5)->values()->map(fn ($question, $index) => [
            'id' => "{$lesson->id}:{$index}",
            'prompt' => $question['prompt'] ?? '',
            'answers' => array_values($question['answers'] ?? []),
            'correctAnswer' => (int) ($question['correctAnswer'] ?? 0),
            'explanation' => $question['explanation'] ?? $lesson->content,
        ]);
    }

    private function payload(KrooIqAttempt $attempt, DailyDestination $destination, $questions): array
    {
        return [
            'date' => $attempt->quiz_date->format('Y-m-d'),
            'destination' => [
                'name' => $destination->country,
                'region' => 'Lesson '.$destination->lesson_number,
                'content' => $destination->content,
                'imageUrl' => ImageUrl::public($destination->image_url),
            ],
            'questions' => $questions->map(fn ($question) => [
                'id' => $question['id'],
                'prompt' => $question['prompt'],
                'answers' => $question['answers'],
            ])->values(),
            'attempt' => $this->attemptData($attempt),
        ];
    }

    private function attemptData(KrooIqAttempt $attempt): array
    {
        return [
            'answers' => $attempt->answers ?? [],
            'correctCount' => $attempt->correct_count,
            'scoreBefore' => (float) $attempt->score_before,
            'scoreAfter' => (float) $attempt->score_after,
            'completed' => (bool) $attempt->completed_at,
        ];
    }

    private function authorizeMember(Request $request): void
    {
        abort_unless(
            app(KrooIqAccess::class)->canUse($request->user()),
            403,
            'Kroo+ membership is required.',
        );
    }
}
