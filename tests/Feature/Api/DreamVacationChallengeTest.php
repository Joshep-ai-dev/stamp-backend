<?php

namespace Tests\Feature\Api;

use App\Models\RevenueCatEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DreamVacationChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_kroo_signups_count_but_pre_membership_signups_do_not(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->member(now()->subMonth());
        User::factory()->create(['referred_by_user_id' => $user->id, 'created_at' => now()->subMonths(2)]);
        User::factory()->count(5)->create(['referred_by_user_id' => $user->id, 'plan' => 'free']);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 5)
            ->assertJsonPath('challengeProgress.referralsQualified', true)
            ->assertJsonPath('challengeProgress.krooIqTarget', 85)
            ->assertJsonPath('challengeProgress.qualified', false);
    }

    public function test_iq_85_is_required_and_lifetime_points_are_excluded(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->member(now()->subMonth());
        $user->krooIqAttempts()->create([
            'quiz_date' => now()->subDays(2)->toDateString(), 'question_ids' => [], 'answers' => [],
            'correct_count' => 0, 'score_before' => 0, 'score_after' => 100,
            'completed_at' => now()->subMonths(2), 'created_at' => now()->subMonths(2),
        ]);
        $attempt = $user->krooIqAttempts()->create([
            'quiz_date' => now()->toDateString(), 'question_ids' => [], 'answers' => [],
            'correct_count' => 0, 'score_before' => 100, 'score_after' => 184.99,
            'completed_at' => now(),
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('challengeProgress.krooIqScore', 84.99)
            ->assertJsonPath('challengeProgress.krooIqQualified', false);
        $attempt->update(['score_after' => 185]);
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.krooIqQualified', true);
    }

    public function test_deadline_includes_the_anniversary_but_excludes_later_activity(): void
    {
        $this->travelTo(now()->startOfSecond());
        $start = now()->subMonthsNoOverflow(12);
        $user = $this->member($start);
        $deadline = $start->copy()->addMonthsNoOverflow(12);
        User::factory()->count(5)->create(['referred_by_user_id' => $user->id, 'created_at' => $deadline]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 5)
            ->assertJsonPath('challengeProgress.expired', false);
        $this->travelTo($deadline->copy()->addSecond());
        User::factory()->create(['referred_by_user_id' => $user->id]);
        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 5)
            ->assertJsonPath('challengeProgress.expired', true);
    }

    public function test_all_targets_within_the_window_qualify_only_an_active_member(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->member(now()->subMonth());
        $user->rewards()->create(['title' => 'Challenge', 'kroo_points' => 5, 'unlocked' => true]);
        User::factory()->count(5)->create(['referred_by_user_id' => $user->id]);
        $user->krooIqAttempts()->create([
            'quiz_date' => now()->toDateString(), 'question_ids' => [], 'answers' => [],
            'correct_count' => 0, 'score_before' => 0, 'score_after' => 85, 'completed_at' => now(),
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.qualified', true);
        $user->revenueCatEntitlement()->update(['is_active' => false]);
        $user->unsetRelation('revenueCatEntitlement');
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.qualified', false);
    }

    public function test_points_and_referrals_after_deadline_cannot_qualify(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = $this->member(now()->subMonths(13));
        $user->rewards()->create(['title' => 'Late challenge', 'kroo_points' => 5, 'unlocked' => true]);
        User::factory()->count(5)->create(['referred_by_user_id' => $user->id]);
        $user->krooIqAttempts()->create([
            'quiz_date' => now()->toDateString(), 'question_ids' => [], 'answers' => [],
            'correct_count' => 0, 'score_before' => 0, 'score_after' => 85, 'completed_at' => now(),
        ]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('challengeProgress.krooScoreQualified', false)
            ->assertJsonPath('challengeProgress.krooIqQualified', false)
            ->assertJsonPath('challengeProgress.referralCount', 0)
            ->assertJsonPath('challengeProgress.qualified', false);
    }

    private function member($start): User
    {
        $user = User::factory()->create();
        RevenueCatEntitlement::create([
            'user_id' => $user->id, 'app_user_id' => $user->id, 'entitlement_id' => 'kroo_plus',
            'product_id' => 'kroo_plus', 'period_type' => 'normal', 'is_active' => true,
            'paid_membership_started_at' => $start, 'expires_at' => now()->addYear(), 'last_verified_at' => now(),
        ]);

        return $user;
    }
}
