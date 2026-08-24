<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_use_token_and_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'Robb', 'email' => 'robb@example.com', 'password' => 'secret-password', 'passwordConfirmation' => 'secret-password']);
        $response->assertCreated()->assertJsonPath('user.language', 'English')->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'language']]);
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('email', 'robb@example.com');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_validates_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Robb', 'email' => 'robb@example.com', 'password' => 'secret-password', 'passwordConfirmation' => 'different'])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
