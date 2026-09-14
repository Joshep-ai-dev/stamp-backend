<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_code_unlocks_guest_access(): void
    {
        $member = User::factory()->create(['friend_code' => 'member-code']);
        foreach (['member-code', 'stampo://friend/member-code'] as $code) {
            $response = $this->postJson('/api/v1/invitations/validate', ['code' => $code])->assertOk();
            $grant = json_decode(Crypt::decryptString($response->json('accessToken')), true);
            $this->assertSame($member->id, $grant['invitedBy']);
        }
    }

    public function test_missing_and_unknown_codes_are_rejected(): void
    {
        $this->postJson('/api/v1/invitations/validate', [])->assertUnprocessable();
        $this->postJson('/api/v1/invitations/validate', ['code' => 'unknown'])->assertUnprocessable();
    }

    public function test_registration_and_code_account_creation_require_an_invitation(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Guest', 'email' => 'guest@example.com',
            'password' => 'secret-password', 'passwordConfirmation' => 'secret-password'])->assertForbidden();
        $this->postJson('/api/v1/auth/code/request', ['email' => 'guest@example.com', 'purpose' => 'create-account'])->assertForbidden();
        $this->postJson('/api/v1/auth/code/verify', ['email' => 'guest@example.com', 'purpose' => 'create-account', 'code' => '123456'])->assertForbidden();
        $this->withHeader('X-Kroo-Invitation', 'forged')->postJson('/api/v1/auth/register', [
            'name' => 'Guest', 'email' => 'guest@example.com', 'password' => 'secret-password',
            'passwordConfirmation' => 'secret-password',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'guest@example.com']);
    }
}
