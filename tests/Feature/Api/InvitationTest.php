<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\KrooId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_code_unlocks_guest_access(): void
    {
        $member = User::factory()->create(['friend_code' => 'member-code']);
        foreach (['member-code', 'stampo://friend/member-code', (string) $member->kroo_id, 'KROO-'.str_pad((string) $member->kroo_id, 10, '0', STR_PAD_LEFT), KrooId::format($member->kroo_id)] as $code) {
            $response = $this->postJson('/api/v1/invitations/validate', ['code' => $code])->assertOk();
            $grant = json_decode(Crypt::decryptString($response->json('accessToken')), true);
            $this->assertSame($member->id, $grant['invitedBy']);
        }
    }

    public function test_invited_traveler_gets_a_persistent_session_and_numeric_kroo_id(): void
    {
        $member = User::factory()->create();

        $response = $this->postJson('/api/v1/invitations/join', [
            'code' => KrooId::format($member->kroo_id),
            'name' => 'Avery',
        ])->assertCreated()
            ->assertJsonPath('user.name', 'Avery')
            ->assertJsonPath('user.email', '')
            ->assertJsonPath('user.emailOptIn', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'krooId', 'formattedKrooId']]);

        $this->assertIsInt($response->json('user.krooId'));
        $this->assertLessThanOrEqual(1_000_000_000, $response->json('user.krooId'));
        $this->assertMatchesRegularExpression('/^(?:[A-Z]\d){5}$/', $response->json('user.formattedKrooId'));
        $this->assertSame($response->json('user.krooId'), KrooId::parse($response->json('user.formattedKrooId')));
        $formattedKrooId = $response->json('user.formattedKrooId');
        $mistypedKrooId = substr($formattedKrooId, 0, 9).(((int) $formattedKrooId[9] + 1) % 10);
        $this->assertNull(KrooId::parse($mistypedKrooId));
        $this->assertDatabaseHas('users', [
            'name' => 'Avery',
            'email_opt_in' => true,
            'referred_by_user_id' => $member->id,
        ]);
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

    public function test_member_session_can_be_restored_from_the_saved_kroo_id(): void
    {
        $member = User::factory()->create(['name' => 'Avery']);

        $response = $this->postJson('/api/v1/members/resume', [
            'krooId' => KrooId::format($member->kroo_id),
        ])->assertOk()
            ->assertJsonPath('user.id', $member->id)
            ->assertJsonPath('user.name', 'Avery')
            ->assertJsonStructure(['token', 'user' => ['krooId', 'formattedKrooId']]);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('id', $member->id);
    }

    public function test_pre_checksum_kroo_id_can_resume_the_same_member(): void
    {
        $member = User::factory()->create();
        $currentCode = KrooId::format($member->kroo_id);
        $legacyCode = substr($currentCode, 0, 8).'Z9';

        $this->postJson('/api/v1/members/resume', ['krooId' => $legacyCode])
            ->assertOk()
            ->assertJsonPath('user.id', $member->id);
    }

    public function test_email_can_be_shared_by_multiple_members(): void
    {
        $current = User::factory()->create(['email' => 'current@example.com']);
        $existing = User::factory()->create(['email' => 'existing@example.com', 'name' => 'Existing Member']);

        $this->actingAs($current)->postJson('/api/v1/members/email', [
            'email' => 'EXISTING@example.com',
            'krooId' => KrooId::format($current->kroo_id),
        ])->assertOk()->assertJsonPath('user.id', $current->id);

        $this->assertDatabaseHas('users', ['id' => $current->id, 'email' => 'existing@example.com']);

        $this->actingAs($existing)->postJson('/api/v1/members/email', [
            'email' => 'EXISTING@example.com',
            'krooId' => KrooId::format($existing->kroo_id),
        ])->assertOk()->assertJsonPath('user.id', $existing->id);
    }

    public function test_new_email_is_attached_to_current_kroo_member(): void
    {
        $current = User::factory()->create();

        $this->actingAs($current)->postJson('/api/v1/members/email', [
            'email' => 'new@example.com',
            'krooId' => KrooId::format($current->kroo_id),
        ])->assertOk()
            ->assertJsonPath('user.id', $current->id);

        $this->assertDatabaseHas('users', ['id' => $current->id, 'email' => 'new@example.com']);
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
