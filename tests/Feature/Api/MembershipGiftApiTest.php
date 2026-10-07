<?php

namespace Tests\Feature\Api;

use App\Models\MembershipGift;
use App\Models\User;
use App\Services\MembershipGifts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipGiftApiTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $transactions = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        config(['services.revenuecat.secret_api_key' => 'secret',
            'services.revenuecat.gift_product_ids' => 'gift.year',
            'services.revenuecat.gift_allow_sandbox' => false]);
        Http::fake(['api.revenuecat.com/*' => Http::response(['subscriber' => [
            'non_subscriptions' => ['gift.year' => $transactions], 'subscriptions' => [], 'entitlements' => [],
        ]])]);
    }
    private function purchase(array $overrides = []): array
    {
        return array_merge(['id' => 'transaction-1', 'purchase_date' => now()->toIso8601String(),
            'store' => 'play_store', 'is_sandbox' => false], $overrides);
    }
    private function draft(User $buyer): string
    {
        Sanctum::actingAs($buyer);
        return $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'friend@example.com', 'message' => 'Happy travels!', 'productId' => 'gift.year',
        ])->assertOk()->assertJsonPath('checkoutAllowed', true)->json('id');
    }
    public function test_verified_gift_sends_note_and_one_code_without_upgrading_buyer(): void
    {
        $this->travelTo(now()->startOfSecond());
        Mail::shouldReceive('raw')->once()->withArgs(fn ($body, $callback) => str_contains($body, 'Happy travels!') && str_contains($body, 'one-use gift code:'));
        $this->subscriber();
        $buyer = User::factory()->create();
        $id = $this->draft($buyer);
        $this->subscriber([$this->purchase()]);
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('emailSent', true);
        $gift = MembershipGift::findOrFail($id);
        $code = $gift->encrypted_code;
        $this->assertSame('free', $buyer->fresh()->plan);
        $this->assertNull($gift->expires_at);
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk();
        $this->assertSame($code, $gift->fresh()->encrypted_code);

        $recipient = User::factory()->create();
        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => $code])->assertOk()->assertJsonPath('isKrooPlus', true);
        $expiry = $gift->fresh()->expires_at;
        $this->assertTrue($expiry->equalTo(now()->addYear()));
        $this->postJson('/api/v1/me/subscription/revenuecat/sync')->assertOk()->assertJsonPath('isKrooPlus', true);
        $this->travel(2)->days();
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => $code])->assertOk();
        $this->assertTrue($gift->fresh()->expires_at->equalTo($expiry));
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => $code])->assertUnprocessable();
        Sanctum::actingAs($recipient);
        $this->travel(367)->days();
        app(MembershipGifts::class)->deliverPending();
        $this->assertSame('free', $recipient->fresh()->plan);
        $this->getJson('/api/v1/me/subscription')->assertOk()->assertJsonPath('isKrooPlus', false);
    }
    public function test_unverified_and_old_payments_do_not_issue_a_code(): void
    {
        $this->subscriber([$this->purchase(['purchase_date' => now()->subDay()->toIso8601String()])]);
        $id = $this->draft(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk()->assertJsonPath('status', 'pending');
        $this->assertNull(MembershipGift::findOrFail($id)->code_hash);
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'friend@example.com', 'message' => 'Happy travels!', 'productId' => 'gift.year',
        ])->assertOk()->assertJsonPath('checkoutAllowed', false)->assertJsonPath('id', $id);
    }
    public function test_refunded_and_sandbox_purchases_are_rejected(): void
    {
        $this->subscriber();
        $id = $this->draft(User::factory()->create());
        $this->subscriber([$this->purchase(['refunded_at' => now()->toIso8601String()]),
            $this->purchase(['id' => 'sandbox', 'is_sandbox' => true])]);
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk()->assertJsonPath('status', 'pending');
    }
    public function test_scheduler_recovers_payment_without_client_confirmation(): void
    {
        Mail::shouldReceive('raw')->once();
        $this->subscriber();
        $id = $this->draft(User::factory()->create());
        $this->subscriber([$this->purchase()]);
        app(MembershipGifts::class)->deliverPending();
        $this->assertNotNull(MembershipGift::findOrFail($id)->email_sent_at);
        $this->assertNotNull(MembershipGift::findOrFail($id)->paid_at);
    }
    public function test_validation_and_ownership(): void
    {
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => 'guess'])->assertUnauthorized();
        $this->subscriber();
        $id = $this->draft(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertNotFound();
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'invalid', 'productId' => 'gift.year',
        ])->assertUnprocessable();
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => 'guess'])->assertUnprocessable();
    }
}
