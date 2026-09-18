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

    private function fakeActiveSubscriber(?CarbonInterface $originalPurchaseDate = null, string $periodType = 'normal'): void
    {
        $originalPurchaseDate ??= now()->subMonth();
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
                            'expires_date' => now()->addMonth()->toIso8601String(),
                        ],
                    ],
                    'subscriptions' => [
                        'kroo_plus_monthly' => [
                            'store' => 'play_store',
                            'period_type' => $periodType,
                            'original_purchase_date' => $originalPurchaseDate->toIso8601String(),
                            'purchase_date' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ]),
        ]);
    }
}
