<?php

namespace Tests\Feature\Api;

use App\Models\RevenueCatEntitlement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenuecat_sync_grants_a_server_entitlement(): void
    {
        $user = User::factory()->create();
        $this->fakeActiveSubscriber();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/subscription/revenuecat/sync')
            ->assertOk()
            ->assertJsonPath('isKrooPlus', true)
            ->assertJsonPath('productId', 'kroo_plus_monthly');

        $this->assertDatabaseHas('revenuecat_entitlements', [
            'user_id' => $user->id,
            'app_user_id' => $user->id,
            'entitlement_id' => 'kroo_plus',
            'is_active' => true,
        ]);
        $this->assertSame('pro', $user->fresh()->plan);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer rc-secret')
            && str_ends_with($request->url(), '/v1/subscribers/'.$user->id)
        );
    }

    public function test_expired_cached_entitlement_is_not_granted(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['plan' => 'pro'])->save();
        RevenueCatEntitlement::create([
            'user_id' => $user->id,
            'app_user_id' => $user->id,
            'entitlement_id' => 'kroo_plus',
            'product_id' => 'kroo_plus_monthly',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
            'last_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('isKrooPlus', false)
            ->assertJsonPath('plan', 'free');
        $this->assertSame('free', $user->fresh()->plan);
    }

    public function test_signed_webhook_refreshes_the_matching_user(): void
    {
        $user = User::factory()->create();
        $this->fakeActiveSubscriber();
        config([
            'services.revenuecat.webhook_authorization' => 'Bearer webhook-secret',
            'services.revenuecat.webhook_signing_secret' => 'signing-secret',
        ]);
        $payload = json_encode(['event' => [
            'id' => 'event-1',
            'type' => 'RENEWAL',
            'app_user_id' => $user->id,
            'aliases' => [],
        ]], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'signing-secret');

        $this->call('POST', '/api/v1/billing/revenuecat/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer webhook-secret',
            'HTTP_X_REVENUECAT_WEBHOOK_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('revenuecat_entitlements', [
            'user_id' => $user->id,
            'last_event_id' => 'event-1',
            'last_event_type' => 'RENEWAL',
        ]);
    }

    public function test_subscription_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/me/subscription')->assertUnauthorized();
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertUnauthorized();
    }

    public function test_paid_member_qualifies_as_a_referral_after_three_months(): void
    {
        $referrer = User::factory()->create();
        $this->givePaidMembership($referrer, now()->subDay());
        $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        $this->fakeActiveSubscriber(now()->subMonths(3)->subDay());

        Sanctum::actingAs($referred);
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertOk();

        $this->assertDatabaseHas('revenuecat_entitlements', [
            'user_id' => $referred->id,
            'is_active' => true,
        ]);
        $this->assertNotNull($referred->revenueCatEntitlement()->first()?->referral_qualified_at);

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 1);
    }

    public function test_trial_and_short_paid_memberships_do_not_count_as_referrals(): void
    {
        $referrer = User::factory()->create();
        $this->givePaidMembership($referrer, now()->subDay());
        $trialMember = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        $paidMember = User::factory()->create(['referred_by_user_id' => $referrer->id]);

        $this->fakeActiveSubscriber(now()->subMonths(4), 'trial');
        Sanctum::actingAs($trialMember);
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertOk();

        $this->fakeActiveSubscriber(now()->subMonths(2));
        Sanctum::actingAs($paidMember);
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertOk();

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 0);
    }

    public function test_active_annual_membership_counts_as_a_referral_immediately(): void
    {
        $referrer = User::factory()->create();
        $this->givePaidMembership($referrer, now()->subDay());
        $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        $this->fakeActiveSubscriber(now(), 'normal', now()->addYear());

        Sanctum::actingAs($referred);
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertOk();

        $this->assertNotNull($referred->revenueCatEntitlement()->first()?->referral_qualified_at);

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 1);
    }

    public function test_google_annual_base_plan_counts_as_a_referral_immediately(): void
    {
        $referrer = User::factory()->create();
        $this->givePaidMembership($referrer, now()->subDay());
        $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        $this->fakeActiveSubscriber(now(), 'normal', now()->addHour(), 'annual', false);

        Sanctum::actingAs($referred);
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')
            ->assertOk()
            ->assertJsonPath('productId', 'kroo_plus_monthly');

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 1);
    }

    public function test_home_dashboard_backfills_an_existing_annual_referral(): void
    {
        $referrer = User::factory()->create();
        $this->givePaidMembership($referrer, now()->subDay());
        $referred = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        RevenueCatEntitlement::create([
            'user_id' => $referred->id,
            'app_user_id' => $referred->id,
            'entitlement_id' => 'kroo_plus',
            'product_id' => 'kroo_plus:annual',
            'store' => 'play_store',
            'period_type' => 'normal',
            'is_active' => true,
            'expires_at' => now()->addYear(),
            'paid_membership_started_at' => now(),
            'last_verified_at' => now(),
            'subscriber_payload' => [
                'subscriber' => [
                    'entitlements' => [
                        'kroo_plus' => [
                            'product_identifier' => 'kroo_plus',
                            'product_plan_identifier' => 'annual',
                        ],
                    ],
                    'subscriptions' => [
                        'kroo_plus:annual' => [
                            'period_type' => 'normal',
                            'purchase_date' => now()->toIso8601String(),
                            'expires_date' => now()->addYear()->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 1);
    }

    public function test_referrals_created_before_the_referrer_joined_kroo_plus_do_not_count(): void
    {
        $referrer = User::factory()->create();
        $referredBefore = User::factory()->create([
            'referred_by_user_id' => $referrer->id,
            'created_at' => now()->subDays(2),
        ]);
        $this->givePaidMembership($referrer, now()->subDay());
        $this->givePaidMembership($referredBefore, now(), now());

        $referredAfter = User::factory()->create(['referred_by_user_id' => $referrer->id]);
        $this->givePaidMembership($referredAfter, now(), now());

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/me/home')
            ->assertOk()
            ->assertJsonPath('challengeProgress.referralCount', 1);
    }

    private function givePaidMembership(
        User $user,
        CarbonInterface $startedAt,
        ?CarbonInterface $qualifiedAt = null,
    ): void {
        RevenueCatEntitlement::create([
            'user_id' => $user->id,
            'app_user_id' => $user->id,
            'entitlement_id' => 'kroo_plus',
            'product_id' => 'kroo_plus',
            'store' => 'play_store',
            'period_type' => 'normal',
            'is_active' => true,
            'expires_at' => now()->addYear(),
            'paid_membership_started_at' => $startedAt,
            'referral_qualified_at' => $qualifiedAt,
            'last_verified_at' => now(),
        ]);
    }

    private function fakeActiveSubscriber(
        ?CarbonInterface $originalPurchaseDate = null,
        string $periodType = 'normal',
        ?CarbonInterface $expiresDate = null,
        ?string $basePlanId = null,
        bool $compositeProductId = true,
    ): void
    {
        $originalPurchaseDate ??= now()->subMonth();
        $expiresDate ??= now()->addMonth();
        config([
            'services.revenuecat.secret_api_key' => 'rc-secret',
            'services.revenuecat.entitlement_id' => 'kroo_plus',
        ]);
        Http::fake([
            'api.revenuecat.com/*' => Http::response([
                'subscriber' => [
                    'entitlements' => [
                        'kroo_plus' => [
                            'product_identifier' => 'kroo_plus_monthly',
                            'product_plan_identifier' => $basePlanId,
                            'expires_date' => $expiresDate->toIso8601String(),
                        ],
                    ],
                    'subscriptions' => [
                        ($basePlanId !== null && $compositeProductId
                            ? "kroo_plus_monthly:{$basePlanId}"
                            : 'kroo_plus_monthly') => [
                            'store' => 'play_store',
                            'period_type' => $periodType,
                            'original_purchase_date' => $originalPurchaseDate->toIso8601String(),
                            'purchase_date' => now()->toIso8601String(),
                            'expires_date' => $expiresDate->toIso8601String(),
                        ],
                    ],
                ],
            ]),
        ]);
    }
}
