<?php

namespace Tests\Feature\Api;

use App\Models\MembershipGift;
use App\Models\RevenueCatEntitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GiftInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function gift(User $buyer, array $attributes = []): MembershipGift
    {
        return MembershipGift::create(array_merge([
            'buyer_id' => $buyer->id, 'recipient_email' => 'friend@example.com',
            'product_id' => 'gift.year', 'baseline_transactions' => [],
            'paid_at' => now(), 'code_hash' => hash('sha256', 'AABBCCDDEEFF112233445566'),
            'encrypted_code' => 'AABBCCDDEEFF112233445566',
        ], $attributes));
    }

    public function test_welcome_gift_code_creates_account_and_starts_prepaid_year(): void
    {
        $this->travelTo(now()->startOfSecond());
        $buyer = User::factory()->create();
        $gift = $this->gift($buyer);
        $this->postJson('/api/v1/invitations/validate', ['code' => $gift->encrypted_code])->assertOk();
        $this->assertNull($gift->fresh()->redeemed_at);
        $this->travel(3)->days();
        $response = $this->postJson('/api/v1/invitations/join', [
            'name' => 'Avery', 'code' => 'aabb-ccdd-eeff-1122-3344-5566',
        ])->assertCreated()->assertJsonPath('user.plan', 'pro')
            ->assertJsonPath('subscription.isKrooPlus', true);
        $recipientId = $response->json('user.id');
        $this->assertDatabaseHas('users', ['id' => $recipientId, 'referred_by_user_id' => $buyer->id]);
        $gift->refresh();
        $this->assertSame($recipientId, $gift->redeemed_by);
        $this->assertTrue($gift->redeemed_at->equalTo(now()));
        $this->assertTrue($gift->expires_at->equalTo(now()->addYear()));
        $this->assertDatabaseCount('friends', 1);
        $this->assertSame('free', $buyer->fresh()->plan);
        $this->withToken($response->json('token'))->getJson('/api/v1/profile')->assertOk()->assertJsonPath('plan', 'pro');
    }

    public function test_consumed_gift_cannot_create_a_second_account(): void
    {
        $gift = $this->gift(User::factory()->create());
        $this->postJson('/api/v1/invitations/join', ['name' => 'Avery', 'code' => $gift->encrypted_code])->assertCreated();
        $this->postJson('/api/v1/invitations/join', ['name' => 'Someone else', 'code' => $gift->encrypted_code])->assertUnprocessable();
        $this->postJson('/api/v1/invitations/validate', ['code' => $gift->encrypted_code])->assertUnprocessable();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_unpaid_and_cancelled_codes_cannot_join(): void
    {
        $gift = $this->gift(User::factory()->create(), ['paid_at' => null]);
        $this->postJson('/api/v1/invitations/join', ['name' => 'Avery', 'code' => $gift->encrypted_code])->assertUnprocessable();
        $gift->forceFill(['paid_at' => now(), 'cancelled_at' => now()])->save();
        $this->postJson('/api/v1/invitations/join', ['name' => 'Avery', 'code' => $gift->encrypted_code])->assertUnprocessable();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_gift_validation_token_claims_once_and_still_checks_current_status(): void
    {
        $gift = $this->gift(User::factory()->create());
        $token = $this->postJson('/api/v1/invitations/validate', ['code' => $gift->encrypted_code])->assertOk()->json('accessToken');
        $grant = json_decode(Crypt::decryptString($token), true);
        $this->assertSame($gift->id, $grant['giftId']);
        $this->withHeader('X-Kroo-Invitation', $token)->postJson('/api/v1/invitations/claim', ['name' => 'Avery'])
            ->assertCreated()->assertJsonPath('user.plan', 'pro');
        $this->withHeader('X-Kroo-Invitation', $token)->postJson('/api/v1/invitations/claim', ['name' => 'Someone else'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_failed_account_creation_does_not_consume_gift_or_leave_account(): void
    {
        $gift = $this->gift(User::factory()->create());
        User::created(function (User $user): void {
            if ($user->name === 'Broken signup') {
                throw new \RuntimeException('Signup failed');
            }
        });
        $this->postJson('/api/v1/invitations/join', ['name' => 'Broken signup', 'code' => $gift->encrypted_code])->assertServerError();
        $this->assertNull($gift->fresh()->redeemed_at);
        $this->assertDatabaseCount('users', 1);
    }

    private function challengeBuyer(): User
    {
        $buyer = User::factory()->create();
        RevenueCatEntitlement::create([
            'user_id' => $buyer->id, 'app_user_id' => $buyer->id,
            'entitlement_id' => 'kroo_plus', 'product_id' => 'kroo_plus_annual',
            'is_active' => true, 'expires_at' => now()->addYear(),
            'paid_membership_started_at' => now()->subDay(), 'last_verified_at' => now(),
        ]);

        return $buyer;
    }

    public function test_gift_signup_increases_buyers_dashboard_referral_count_exactly_once(): void
    {
        $buyer = $this->challengeBuyer();
        $gift = $this->gift($buyer);
        $this->postJson('/api/v1/invitations/join', [
            'name' => 'New gift member', 'code' => $gift->encrypted_code,
        ])->assertCreated();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.referralCount', 1);
        $this->assertSame(1, $buyer->referrals()->count());
        $this->postJson('/api/v1/invitations/join', [
            'name' => 'Duplicate signup', 'code' => $gift->encrypted_code,
        ])->assertUnprocessable();
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.referralCount', 1);
    }

    public function test_existing_member_gift_does_not_credit_buyer_or_replace_original_referrer(): void
    {
        $buyer = $this->challengeBuyer();
        $originalReferrer = User::factory()->create();
        $recipient = User::factory()->create(['referred_by_user_id' => $originalReferrer->id]);
        $gift = $this->gift($buyer);
        Sanctum::actingAs($recipient);
        $this->postJson('/api/v1/me/membership-gifts/redeem', ['code' => $gift->encrypted_code])
            ->assertOk()->assertJsonPath('isKrooPlus', true);
        $this->assertSame($originalReferrer->id, $recipient->fresh()->referred_by_user_id);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/me/home')->assertOk()->assertJsonPath('challengeProgress.referralCount', 0);
        $this->assertSame(0, $buyer->referrals()->count());
    }
}
