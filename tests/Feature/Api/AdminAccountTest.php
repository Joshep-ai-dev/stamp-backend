<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stampo.admin_key' => 'account-test']);
    }

    public function test_accounts_require_the_admin_key_and_hide_credentials(): void
    {
        $user = User::factory()->create(['email' => 'find@example.com']);
        $this->getJson('/admin/api/accounts')->assertUnauthorized();
        $this->deleteJson('/admin/api/accounts/'.$user->id, ['confirmation' => $user->email])->assertUnauthorized();
        $this->withHeader('X-Admin-Key', 'account-test')
            ->getJson('/admin/api/accounts?query=FIND%40EXAMPLE.COM')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $user->id)
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_deletion_requires_confirmation_and_cleans_up_account_data(): void
    {
        $user = User::factory()->create(['name' => 'Robb', 'family_name' => 'Arbuckle']);
        $other = User::factory()->create(['referred_by_user_id' => $user->id]);
        $user->createToken('device');
        DB::table('sessions')->insert(['id' => 'session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset']);
        DB::table('wishlists')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'target_id' => 'test', 'saved_at' => now()]);
        DB::table('membership_gifts')->insert([
            'id' => (string) Str::uuid(), 'buyer_id' => $user->id, 'redeemed_by' => $user->id,
            'recipient_email' => $other->email, 'product_id' => 'gift', 'baseline_transactions' => '[]', 'redeemed_at' => now(),
        ]);
        $this->withHeader('X-Admin-Key', 'account-test');
        $path = '/admin/api/accounts/'.$user->id;
        $this->deleteJson($path)->assertUnprocessable();
        $this->deleteJson($path, ['confirmation' => 'wrong'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->deleteJson($path, ['confirmation' => $user->email])->assertUnprocessable();
        $this->deleteJson($path, ['confirmation' => 'Robb Arbuckle'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('wishlists', 0);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'referred_by_user_id' => null]);
        $this->assertDatabaseHas('membership_gifts', ['buyer_id' => null, 'redeemed_by' => null]);
        $this->deleteJson($path, ['confirmation' => 'Robb Arbuckle'])->assertNotFound();
    }

    public function test_account_without_email_can_be_deleted_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Kevin', 'family_name' => null, 'email' => null]);
        $other = User::factory()->create(['name' => 'Kevin', 'email' => null]);
        $this->withHeader('X-Admin-Key', 'account-test')
            ->deleteJson('/admin/api/accounts/'.$user->id, ['confirmation' => 'Kevin'])
            ->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }
}
