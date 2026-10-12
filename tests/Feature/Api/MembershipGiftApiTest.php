<?php

namespace Tests\Feature\Api;

use App\Mail\MembershipGiftMail;
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
        Mail::fake();
        $this->subscriber();
        $buyer = User::factory()->create();
        $id = $this->draft($buyer);
        $this->subscriber([$this->purchase()]);
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('emailSent', true);
        $gift = MembershipGift::findOrFail($id);
        $code = $gift->encrypted_code;
        Mail::assertSent(MembershipGiftMail::class, fn ($mail) => $mail->hasTo('friend@example.com') && ! $mail->isBuyer);
        Mail::assertSent(MembershipGiftMail::class, fn ($mail) => $mail->hasTo($buyer->email) && $mail->isBuyer);
        $recipientHtml = (new MembershipGiftMail($gift, '<b>Sender</b>'))->render();
        $this->assertStringContainsString('Happy travels!', $recipientHtml);
        $this->assertStringContainsString($code, $recipientHtml);
        $this->assertStringContainsString('Gift code:', $recipientHtml);
        $this->assertStringNotContainsString('Gift reference:', $recipientHtml);
        $this->assertStringNotContainsString('Activate Your Gift', $recipientHtml);
        $this->assertStringContainsString('&lt;b&gt;Sender&lt;/b&gt;', $recipientHtml);
        $this->assertStringNotContainsString($code, (new MembershipGiftMail($gift, $buyer->name, true))->render());
        $this->assertNotNull($gift->buyer_email_sent_at);
        $this->assertSame('free', $buyer->fresh()->plan);
        $this->assertNull($gift->expires_at);
        $this->postJson('/api/v1/me/membership-gifts/'.$id.'/verify')->assertOk();
        $this->assertSame($code, $gift->fresh()->encrypted_code);
        Mail::assertSentCount(2);

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
    public function test_emails_show_recipient_name_personal_note_and_inline_code(): void
    {
        $buyer = User::factory()->create(['name' => 'Robert']);
        User::factory()->create(['name' => 'Sarah', 'email' => 'friend@example.com']);
        $gift = MembershipGift::create([
            'buyer_id' => $buyer->id, 'recipient_email' => 'friend@example.com',
            'product_id' => 'gift.year', 'baseline_transactions' => [], 'paid_at' => now(),
            'encrypted_code' => 'AABBCCDDEEFF112233445566', 'message' => "Safe <note>\nHappy travels!",
        ]);
        $buyerHtml = (new MembershipGiftMail($gift, 'Robert', true))->render();
        $recipientHtml = (new MembershipGiftMail($gift, 'Robert'))->render();
        $this->assertStringContainsString('Recipient:</strong> Sarah', $buyerHtml);
        $this->assertStringNotContainsString('friend@example.com', $buyerHtml);
        $this->assertStringContainsString('Your personal note:', $buyerHtml);
        foreach ([$buyerHtml, $recipientHtml] as $html) {
            $this->assertStringContainsString('Safe &lt;note&gt;<br', $html);
            $this->assertStringContainsString('Happy travels!', $html);
            $this->assertStringNotContainsString('Clipboard', $html);
            $this->assertStringNotContainsString('&#128203;', $html);
        }
        $this->assertStringContainsString('Gift code:</strong> <span style="font-family:monospace;font-weight:normal;', $recipientHtml);
        $gift->recipient_email = 'new-friend@example.com';
        $fallbackHtml = (new MembershipGiftMail($gift, 'Robert', true))->render();
        $this->assertStringContainsString('Recipient:</strong> your friend', $fallbackHtml);
        $this->assertStringNotContainsString('new-friend@example.com', $fallbackHtml);
    }

    public function test_buyer_confirmation_retries_without_resending_recipient_code(): void
    {
        Mail::fake();
        $buyer = User::factory()->create();
        $gift = MembershipGift::create([
            'buyer_id' => $buyer->id, 'recipient_email' => 'friend@example.com',
            'product_id' => 'gift.year', 'baseline_transactions' => [],
            'paid_at' => now(), 'email_sent_at' => now(),
            'encrypted_code' => 'PRIVATE-GIFT-CODE',
        ]);
        app(MembershipGifts::class)->deliverPending();
        Mail::assertSent(MembershipGiftMail::class, fn ($mail) => $mail->isBuyer && $mail->hasTo($buyer->email));
        Mail::assertSentCount(1);
        $this->assertNotNull($gift->fresh()->buyer_email_sent_at);
        app(MembershipGifts::class)->deliverPending();
        Mail::assertSentCount(1);
    }

    public function test_gift_requires_buyers_email_before_contacting_payment_provider(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['email' => null]));
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'friend@example.com', 'productId' => 'gift.year',
        ])->assertUnprocessable()->assertJsonPath('message', 'Add a valid email address to your Kroo account before buying a gift.');
        Http::assertNothingSent();
        $this->assertDatabaseCount('membership_gifts', 0);
    }

    public function test_gift_can_be_purchased_for_a_friend_without_a_kroo_account(): void
    {
        $this->subscriber();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'new-friend@example.com', 'productId' => 'gift.year',
        ])->assertOk()->assertJsonPath('checkoutAllowed', true)
            ->assertJsonPath('recipientEmail', 'new-friend@example.com');
        $this->assertDatabaseMissing('users', ['email' => 'new-friend@example.com']);
        $this->assertDatabaseHas('membership_gifts', ['recipient_email' => 'new-friend@example.com']);
    }

    public function test_invalid_recipient_email_is_rejected_before_payment(): void
    {
        Http::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'not-an-email', 'productId' => 'gift.year',
        ])->assertUnprocessable()->assertJsonValidationErrors('recipientEmail');
        Http::assertNothingSent();
        $this->assertDatabaseCount('membership_gifts', 0);
    }

    public function test_registered_recipient_email_matches_without_case_sensitivity(): void
    {
        $this->subscriber();
        User::factory()->create(['email' => 'Friend@Example.com']);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/me/membership-gifts', [
            'recipientEmail' => 'friend@example.com', 'productId' => 'gift.year',
        ])->assertOk()->assertJsonPath('checkoutAllowed', true);
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
        Mail::fake();
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
