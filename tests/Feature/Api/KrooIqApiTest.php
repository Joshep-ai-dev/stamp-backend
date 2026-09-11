<?php

namespace Tests\Feature\Api;

use App\Models\DailyDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KrooIqApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kroo_iq_requires_authentication_and_kroo_plus(): void
    {
        $this->getJson('/api/v1/me/kroo-iq/today')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['plan' => 'free']));
        $this->getJson('/api/v1/me/kroo-iq/today')->assertForbidden();
    }

    public function test_kroo_plus_requirement_can_be_temporarily_disabled(): void
    {
        config(['features.kroo_iq_requires_kroo_plus' => false]);
        Sanctum::actingAs(User::factory()->create(['plan' => 'free']));

        $this->getJson('/api/v1/me/kroo-iq/today')->assertNotFound();
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
}
