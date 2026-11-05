<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'created_at']])
            ->assertJsonPath('user.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_user_can_login_and_use_the_token_on_protected_routes(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_reject_unauthenticated_requests_with_json_401(): void
    {
        // No Accept header on purpose: API errors must still be JSON, not a redirect.
        $this->get('/api/products')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_login_is_rate_limited(): void
    {
        $payload = ['email' => 'nobody@example.com', 'password' => 'x', 'device_name' => 'phpunit'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', $payload)->assertTooManyRequests();
    }
}

