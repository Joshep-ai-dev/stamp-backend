<?php

namespace Tests\Feature\Api;

use App\Models\DailyDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KrooIqApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kroo_iq_requires_authentication_and_free_user_needs_preview_lesson(): void
    {
        $this->getJson('/api/v1/me/kroo-iq/today')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['plan' => 'free']));
        $this->getJson('/api/v1/me/kroo-iq/today')->assertNotFound();
    }

    public function test_free_user_receives_ten_question_lesson_zero_preview(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        Sanctum::actingAs($user);
        DailyDestination::create([
            'id' => 'public-preview', 'name' => 'Lesson 0 - Thailand', 'country' => 'Thailand',
            'content' => 'Preview lesson.', 'question' => 'Question 1?',
            'options' => ['Correct', 'Wrong'], 'correct_answer' => 0,
            'questions' => collect(range(1, 10))->map(fn ($number) => [
                'prompt' => "Question {$number}?", 'imageUrl' => "/storage/daily-destinations/q{$number}.jpg",
                'answers' => ['Correct', 'Wrong'], 'correctAnswer' => 0, 'explanation' => 'Preview lesson.',
            ])->all(),
            'lesson_number' => 0, 'is_published' => true,
        ]);

        $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()
            ->assertJsonPath('isPreview', true)
            ->assertJsonCount(10, 'questions')
            ->assertJsonPath('questions.0.imageUrl', '/storage/daily-destinations/q1.jpg');
    }

    public function test_kroo_plus_requirement_can_be_temporarily_disabled(): void
    {
        config(['features.kroo_iq_requires_kroo_plus' => false]);
        Sanctum::actingAs(User::factory()->create(['plan' => 'free']));

        $this->getJson('/api/v1/me/kroo-iq/today')->assertNotFound();
    }

    public function test_preview_is_free_once_then_kroo_plus_unlocks_the_next_lesson_on_another_day(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');
        $user = User::factory()->create(['plan' => 'free']);
        Sanctum::actingAs($user);

        DailyDestination::create([
            'id' => 'preview', 'name' => 'Lesson 0', 'country' => 'Thailand', 'content' => 'Preview',
            'question' => 'Preview?', 'options' => ['A', 'B'], 'correct_answer' => 0,
            'questions' => collect(range(1, 10))->map(fn ($number) => ['prompt' => "Preview {$number}?", 'answers' => ['A', 'B'], 'correctAnswer' => 0, 'explanation' => 'Preview'])->all(),
            'lesson_number' => 0, 'is_published' => true,
        ]);
        DailyDestination::create([
            'id' => 'lesson-1', 'name' => 'Lesson 1', 'country' => 'Japan', 'content' => 'Lesson',
            'question' => 'Lesson?', 'options' => ['A', 'B'], 'correct_answer' => 0,
            'questions' => collect(range(1, 5))->map(fn ($number) => ['prompt' => "Lesson {$number}?", 'answers' => ['A', 'B'], 'correctAnswer' => 0, 'explanation' => 'Lesson'])->all(),
            'lesson_number' => 1, 'is_published' => true,
        ]);

        $preview = $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()->assertJsonPath('isPreview', true);
        foreach (collect($preview->json('questions'))->pluck('id') as $id) {
            $this->postJson('/api/v1/me/kroo-iq/answer', ['questionId' => $id, 'selectedAnswer' => 0])->assertOk();
        }

        Carbon::setTestNow('2026-09-12 09:00:00');
        $this->getJson('/api/v1/me/kroo-iq/today')->assertForbidden()
            ->assertJsonPath('message', 'You completed the free preview. Join Kroo+ to unlock Lesson 1.');

        $user->forceFill(['plan' => 'pro'])->save();
        $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()
            ->assertJsonPath('isPreview', false)
            ->assertJsonPath('destination.region', 'Lesson 1');

        Carbon::setTestNow();
    }

    public function test_member_can_complete_daily_quiz_and_score_is_persisted(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        Sanctum::actingAs($user);
        DailyDestination::create([
            'id' => 'thailand-lesson', 'name' => 'Lesson 1 - Thailand', 'country' => 'Thailand',
            'content' => 'Travel lesson.', 'question' => 'Question 1?',
            'options' => ['Correct', 'Wrong'], 'correct_answer' => 0,
            'questions' => collect(range(1, 5))->map(fn ($number) => ['prompt' => "Question {$number}?", 'answers' => ['Correct', 'Wrong'], 'correctAnswer' => 0, 'explanation' => 'Travel lesson.'])->all(),
            'lesson_number' => 1, 'publish_date' => now()->toDateString(), 'is_published' => true,
        ]);

        $quiz = $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()
            ->assertJsonCount(5, 'questions')->assertJsonMissingPath('questions.0.correctAnswer');
        $ids = collect($quiz->json('questions'))->pluck('id');

        foreach ($ids as $offset => $id) {
            $this->postJson('/api/v1/me/kroo-iq/answer', ['questionId' => $id, 'selectedAnswer' => 0])
                ->assertOk()->assertJsonPath('correct', true)
                ->assertJsonPath('attempt.correctCount', $offset + 1);
        }

        $this->assertDatabaseHas('kroo_iq_attempts', ['user_id' => $user->id, 'correct_count' => 5]);
        $this->assertSame('7.60', $user->fresh()->kroo_iq_score);
        $this->getJson('/api/v1/me/kroo-iq/today')->assertJsonPath('attempt.completed', true);
    }

    public function test_answers_must_be_submitted_once_and_in_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['plan' => 'pro']));
        DailyDestination::create(['id' => 'ordered', 'name' => 'Lesson 1 - Thailand', 'country' => 'Thailand', 'content' => 'Lesson', 'question' => 'Question?', 'options' => ['A', 'B'], 'correct_answer' => 0, 'questions' => collect(range(1, 5))->map(fn ($number) => ['prompt' => "Question {$number}?", 'answers' => ['A', 'B'], 'correctAnswer' => 0, 'explanation' => 'Lesson'])->all(), 'lesson_number' => 1, 'is_published' => true]);
        $ids = collect($this->getJson('/api/v1/me/kroo-iq/today')->json('questions'))->pluck('id');
        $this->postJson('/api/v1/me/kroo-iq/answer', ['questionId' => $ids[1], 'selectedAnswer' => 0])->assertConflict();
        $this->postJson('/api/v1/me/kroo-iq/answer', ['questionId' => $ids[0], 'selectedAnswer' => 0])->assertOk();
        $this->postJson('/api/v1/me/kroo-iq/answer', ['questionId' => $ids[0], 'selectedAnswer' => 0])->assertConflict();
    }

    public function test_each_member_progresses_through_lessons_in_order_from_lesson_one(): void
    {
        Carbon::setTestNow('2026-09-11 09:00:00');

        foreach (range(1, 3) as $number) {
            DailyDestination::create([
                'id' => "lesson-{$number}",
                'name' => "Lesson {$number}",
                'country' => "Country {$number}",
                'content' => 'Lesson',
                'question' => 'Question?',
                'options' => ['A', 'B'],
                'correct_answer' => 0,
                'questions' => [['prompt' => 'Question?', 'answers' => ['A', 'B'], 'correctAnswer' => 0, 'explanation' => 'Lesson']],
                'lesson_number' => $number,
                'is_published' => true,
            ]);
        }

        $firstUser = User::factory()->create(['plan' => 'pro']);
        Sanctum::actingAs($firstUser);
        $firstLesson = $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()->json('questions.0.id');
        $this->assertStringStartsWith('lesson-1:', $firstLesson);
        $this->assertSame($firstLesson, $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()->json('questions.0.id'));

        Carbon::setTestNow('2026-09-12 09:00:00');
        $secondLesson = $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()->json('questions.0.id');
        $this->assertStringStartsWith('lesson-2:', $secondLesson);

        $secondUser = User::factory()->create(['plan' => 'pro']);
        Sanctum::actingAs($secondUser);
        $newMemberLesson = $this->getJson('/api/v1/me/kroo-iq/today')->assertOk()->json('questions.0.id');
        $this->assertStringStartsWith('lesson-1:', $newMemberLesson);

        Carbon::setTestNow();
    }
}
