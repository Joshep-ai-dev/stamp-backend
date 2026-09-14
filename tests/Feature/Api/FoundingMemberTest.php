<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundingMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_member_can_unlock_the_app_without_an_existing_inviter(): void
    {
        $this->artisan('kroo:founding-member', ['email' => 'Founder@example.com', '--name' => 'Founder'])->assertSuccessful();
        $member = User::where('email', 'founder@example.com')->firstOrFail();
        $this->assertSame('Founder', $member->name);
        $this->assertNull($member->email_verified_at);
        $this->assertSame(36, strlen($member->friend_code));
        $this->postJson('/api/v1/invitations/validate', ['code' => $member->friend_code])
            ->assertOk()->assertJsonStructure(['accessToken']);
    }

    public function test_rerunning_preserves_existing_account_and_invitation(): void
    {
        $member = User::factory()->create(['email' => 'founder@example.com', 'friend_code' => 'existing-code']);
        $before = $member->fresh()->getAttributes();
        $this->artisan('kroo:founding-member', ['email' => $member->email, '--name' => 'Different name'])
            ->expectsOutput('Referral code: existing-code')->assertSuccessful();
        $this->assertSame($before, $member->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_member_without_a_code_gets_one(): void
    {
        $member = User::factory()->create(['friend_code' => null]);
        $this->artisan('kroo:founding-member', ['email' => $member->email])->assertSuccessful();
        $this->assertNotEmpty($member->fresh()->friend_code);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_input_does_not_create_an_account(): void
    {
        $this->artisan('kroo:founding-member', ['email' => 'invalid', '--name' => 'Founder'])->assertFailed();
        $this->artisan('kroo:founding-member', ['email' => 'founder@example.com'])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
