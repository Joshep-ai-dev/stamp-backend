<?php

namespace Tests\Feature\Api;

use App\Models\RevenueCatEntitlement;
use App\Models\User;
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
        Http::assertSent(fn ($request): bool =>
            $request->hasHeader('Authorization', 'Bearer rc-secret')
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

    private function fakeActiveSubscriber(): void
    {
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
                            'period_type' => 'normal',
                        ],
                    ],
                ],
            ]),
        ]);
    }
}
