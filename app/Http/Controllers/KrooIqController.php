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
        $questions = $this->questionsFor($date);
        abort_if($questions->isEmpty(), 404, 'Today\'s Kroo IQ quiz is not available yet.');

        $attempt = KrooIqAttempt::firstOrCreate(
            ['user_id' => $request->user()->id, 'quiz_date' => $date],
            [
                'question_ids' => $questions->pluck('id')->values()->all(),
                'answers' => [],
                'score_before' => $request->user()->kroo_iq_score,
                'score_after' => $request->user()->kroo_iq_score,
            ],
        );

        $questions = DailyDestination::whereIn('id', $attempt->question_ids)->get()
            ->sortBy(fn ($item) => array_search($item->id, $attempt->question_ids, true))->values();

        return response()->json($this->payload($attempt, $questions));
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

            $question = DailyDestination::findOrFail($data['questionId']);
            abort_if($data['selectedAnswer'] >= count($question->options ?? []), 422, 'The selected answer is invalid.');
            $isCorrect = $data['selectedAnswer'] === $question->correct_answer;
            $answers[] = ['questionId' => $question->id, 'selectedAnswer' => $data['selectedAnswer'], 'correct' => $isCorrect];
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
                'correctAnswer' => $question->correct_answer,
                'explanation' => $this->explanation($question),
                'attempt' => $this->attemptData($attempt),
            ];
        });

        return response()->json($payload);
    }

    private function questionsFor(string $date)
    {
        return DailyDestination::where('is_published', true)
            ->where(fn ($query) => $query->whereDate('publish_date', $date)->orWhereNull('publish_date'))
            ->orderByRaw('case when publish_date = ? then 0 else 1 end', [$date])
            ->orderBy('display_order')->orderBy('name')->limit(5)->get();
    }

    private function payload(KrooIqAttempt $attempt, $questions): array
    {
        $destination = $questions->first();
        return [
            'date' => $attempt->quiz_date->format('Y-m-d'),
            'destination' => [
                'name' => $destination->country ?: $destination->name,
                'region' => $destination->city ?: 'Explore today\'s destination',
                'content' => $destination->content,
                'imageUrl' => ImageUrl::public($destination->image_url),
            ],
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id,
                'prompt' => $question->question,
                'answers' => $question->options,
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

    private function explanation(DailyDestination $question): string
    {
        $answer = $question->options[$question->correct_answer] ?? '';
        return $answer ? "The correct answer is {$answer}. {$question->content}" : $question->content;
    }

    private function authorizeMember(Request $request): void
    {
        // abort_unless(
        //     app(KrooIqAccess::class)->canUse($request->user()),
        //     403,
        //     'Kroo+ membership is required.',
        // );
    }
}
