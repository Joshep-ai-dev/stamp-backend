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
        foreach (['member-code', 'stampo://friend/member-code', (string) $member->kroo_id, 'KROO-'.str_pad((string) $member->kroo_id, 10, '0', STR_PAD_LEFT)] as $code) {
            $response = $this->postJson('/api/v1/invitations/validate', ['code' => $code])->assertOk();
            $grant = json_decode(Crypt::decryptString($response->json('accessToken')), true);
            $this->assertSame($member->id, $grant['invitedBy']);
        }
    }

    public function test_invited_traveler_gets_a_persistent_session_and_numeric_kroo_id(): void
    {
        $member = User::factory()->create();

        $response = $this->postJson('/api/v1/invitations/join', [
            'code' => 'KROO-'.str_pad((string) $member->kroo_id, 10, '0', STR_PAD_LEFT),
            'name' => 'Avery',
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Avery')
            ->assertJsonPath('user.email', '')
            ->assertJsonPath('user.emailOptIn', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'krooId', 'formattedKrooId']]);

        $this->assertIsInt($response->json('user.krooId'));
        $this->assertLessThanOrEqual(1_000_000_000, $response->json('user.krooId'));
        $this->assertMatchesRegularExpression('/^KROO-\d{10}$/', $response->json('user.formattedKrooId'));
        $this->assertDatabaseHas('users', ['name' => 'Avery', 'email_opt_in' => true]);
        $this->assertDatabaseCount('friends', 1);

        $headers = ['Authorization' => 'Bearer '.$response->json('token')];
        $this->withHeaders($headers)->getJson('/api/v1/profile')->assertOk()
            ->assertJsonPath('formattedKrooId', $response->json('user.formattedKrooId'))
            ->assertJsonPath('emailOptIn', true);
        $this->withHeaders($headers)->putJson('/api/v1/profile', ['email' => '', 'emailOptIn' => false])->assertOk()
            ->assertJsonPath('email', '')
            ->assertJsonPath('emailOptIn', false);
        $this->withHeaders($headers)->getJson('/api/v1/me/friend-code')->assertOk()
            ->assertJsonPath('code', $response->json('user.formattedKrooId'));
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
