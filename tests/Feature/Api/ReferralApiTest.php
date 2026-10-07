<?php

namespace Tests\Feature\Api;

use App\Models\MembershipGift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_own_referred_members_are_listed_with_join_source(): void
    {
        $buyer = User::factory()->create();
        $ordinary = User::factory()->create(['referred_by_user_id' => $buyer->id]);
        $gift = MembershipGift::create([
            'buyer_id' => $buyer->id, 'recipient_email' => 'friend@example.com',
            'product_id' => 'gift.year', 'baseline_transactions' => [], 'paid_at' => now(),
            'code_hash' => hash('sha256', 'AABBCCDDEEFF112233445566'),
            'encrypted_code' => 'AABBCCDDEEFF112233445566',
        ]);
        $recipient = $this->postJson('/api/v1/invitations/join', [
            'name' => 'Gift member', 'code' => $gift->encrypted_code,
        ])->assertCreated()->json('user.id');
        $other = User::factory()->create();
        User::factory()->create(['referred_by_user_id' => $other->id]);
        Sanctum::actingAs($buyer);
        $response = $this->getJson('/api/v1/me/referrals')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'members')->assertJsonPath('nextPage', null);
        $members = collect($response->json('members'))->keyBy('id');
        $this->assertSame('gift', $members[$recipient]['source']);
        $this->assertSame('referral', $members[$ordinary->id]['source']);
        $this->assertArrayNotHasKey('email', $members[$recipient]);
        $this->assertNotEmpty($members[$recipient]['joinedAt']);
    }

    public function test_authentication_empty_state_and_pagination(): void
    {
        $this->getJson('/api/v1/me/referrals')->assertUnauthorized();
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/me/referrals')->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'members');
        User::factory()->count(51)->create(['referred_by_user_id' => $buyer->id]);
        $first = $this->getJson('/api/v1/me/referrals')->assertOk()->assertJsonCount(50, 'members')
            ->assertJsonPath('total', 51)->assertJsonPath('nextPage', 2);
        $last = $this->getJson('/api/v1/me/referrals?page=2')->assertOk()->assertJsonCount(1, 'members')
            ->assertJsonPath('nextPage', null);
        $this->assertNotContains($last->json('members.0.id'), array_column($first->json('members'), 'id'));
        $this->getJson('/api/v1/me/referrals?page=0')->assertUnprocessable();
    }
}
