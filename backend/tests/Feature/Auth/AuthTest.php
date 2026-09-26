<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_a_token(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonMissingPath('user.password');

        $this->assertCount(1, $user->tokens);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);

        $this->assertCount(0, $user->tokens);
    }

    public function test_login_with_unknown_email_returns_the_same_error_as_wrong_password(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_register_creates_a_staff_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token'])
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonMissingPath('user.password');

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertSame('staff', $user->role);
        $this->assertCount(1, $user->tokens);

        // The returned token is immediately usable.
        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_register_validates_required_fields_and_password_confirmation(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_ignores_a_client_supplied_admin_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sneaky Person',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'admin',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'staff');

        $user = User::where('email', 'sneaky@example.com')->sole();
        $this->assertSame('staff', $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_protected_routes_reject_unauthenticated_requests_with_401(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->postJson('/api/logout')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_request_without_json_accept_header_gets_401_not_a_redirect(): void
    {
        // Regression: the auth middleware used to try redirecting guests to a
        // non-existent "login" route. API requests must always get a JSON 401.
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_invalid_token_is_rejected_with_401(): void
    {
        $this->withToken('1|not-a-real-token')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_invalidates_the_token(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        $token = $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The guard caches the resolved user across requests within one test;
        // reset it so the next request genuinely re-authenticates the token.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_only_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $laptopToken = $user->createToken('laptop')->plainTextToken;
        $phoneToken = $user->createToken('phone')->plainTextToken;

        $this->withToken($laptopToken)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($laptopToken)->getJson('/api/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($phoneToken)->getJson('/api/me')->assertOk();
    }
}
