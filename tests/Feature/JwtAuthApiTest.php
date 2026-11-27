<?php

namespace Tests\Feature;

use App\Mail\APIResetPassword;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class JwtAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user', array $attributes = []): Pengguna
    {
        return Pengguna::factory()->create(array_merge([
            'username' => $role.'1',
            'email' => $role.'1@example.com',
            'role' => $role,
            'password' => Hash::make('secret123'),
        ], $attributes));
    }

    private function loginToken(string $username, string $password = 'secret123'): string
    {
        $response = $this->postJson('/api/auth/login', ['username' => $username, 'password' => $password]);
        $response->assertOk();

        return $response->json('access_token');
    }

    /**
     * Requests in one test share the app: drop the guard's cached user and the parsed token,
     * as a new HTTP request would.
     */
    private function forgetAuthState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetScopedInstances();
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_register_creates_a_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ali',
            'username' => 'ali',
            'email' => 'ali@example.com',
            'role' => 'admin',
            'password' => 'password1',
        ]);

        $response->assertCreated()
            ->assertJson(['name' => 'Ali', 'username' => 'ali', 'email' => 'ali@example.com', 'role' => 'admin'])
            ->assertJsonMissingPath('password');

        $user = Pengguna::find('ali');
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password1', $user->password));
    }

    public function test_register_validates_input(): void
    {
        $this->makeUser('user');

        $this->postJson('/api/auth/register', [
            'name' => 'Dup',
            'username' => 'user1',
            'email' => 'user1@example.com',
            'role' => 'boss',
            'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'role', 'password']);
    }

    public function test_login_returns_a_bearer_token_with_the_role_claim(): void
    {
        $this->makeUser('admin');

        $response = $this->postJson('/api/auth/login', ['username' => 'admin1', 'password' => 'secret123']);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJson(['token_type' => 'bearer', 'expires_in' => 3600]);

        $payload = JWTAuth::setToken($response->json('access_token'))->getPayload();
        $this->assertSame('admin1', $payload->get('sub'));
        $this->assertSame('admin', $payload->get('role'));
    }

    public function test_login_with_a_wrong_password_is_unauthorized(): void
    {
        $this->makeUser('user');

        $this->postJson('/api/auth/login', ['username' => 'user1', 'password' => 'wrong-pass'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_login_with_an_unknown_username_is_unauthorized(): void
    {
        $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'secret123'])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_me_returns_the_signed_in_user(): void
    {
        $this->makeUser('user');
        $token = $this->loginToken('user1');

        $this->postJson('/api/auth/me', [], $this->bearer($token))
            ->assertOk()
            ->assertJson(['username' => 'user1', 'email' => 'user1@example.com', 'role' => 'user'])
            ->assertJsonMissingPath('password');
    }

    public function test_get_user_returns_the_signed_in_user(): void
    {
        $this->makeUser('user');
        $token = $this->loginToken('user1');

        $this->getJson('/api/user', $this->bearer($token))
            ->assertOk()
            ->assertJson(['username' => 'user1']);
    }

    public function test_protected_endpoints_need_a_token(): void
    {
        foreach (['/api/auth/me', '/api/auth/refresh', '/api/auth/logout', '/api/iamadmin', '/api/iamuser'] as $uri) {
            $this->postJson($uri)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
        }

        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/auth/me', [], $this->bearer('not-a-jwt'))->assertUnauthorized();
    }

    public function test_refresh_issues_a_new_token_and_blacklists_the_old_one(): void
    {
        $this->makeUser('user');
        $old = $this->loginToken('user1');

        $response = $this->postJson('/api/auth/refresh', [], $this->bearer($old));
        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJson(['token_type' => 'bearer']);

        $new = $response->json('access_token');
        $this->assertNotSame($old, $new);

        $this->forgetAuthState();
        $this->postJson('/api/auth/me', [], $this->bearer($new))->assertOk()->assertJson(['username' => 'user1']);

        $this->forgetAuthState();
        $this->postJson('/api/auth/me', [], $this->bearer($old))->assertUnauthorized();
    }

    public function test_logout_invalidates_the_token(): void
    {
        $this->makeUser('user');
        $token = $this->loginToken('user1');

        $this->postJson('/api/auth/logout', [], $this->bearer($token))
            ->assertOk()
            ->assertExactJson(['message' => 'Successfully logged out']);

        $this->forgetAuthState();
        $this->postJson('/api/auth/me', [], $this->bearer($token))->assertUnauthorized();
    }

    public function test_admin_route_allows_admins_only(): void
    {
        $this->makeUser('admin');
        $this->makeUser('user');

        $this->postJson('/api/iamadmin', [], $this->bearer($this->loginToken('admin1')))
            ->assertOk()->assertExactJson(['data' => 'You are admin!']);

        $this->forgetAuthState();
        $this->postJson('/api/iamadmin', [], $this->bearer($this->loginToken('user1')))
            ->assertForbidden()->assertExactJson(['status' => false, 'message' => 'Unauthorized']);
    }

    public function test_user_route_allows_users_only(): void
    {
        $this->makeUser('admin');
        $this->makeUser('user');

        $this->postJson('/api/iamuser', [], $this->bearer($this->loginToken('user1')))
            ->assertOk()->assertExactJson(['data' => 'You are user!']);

        $this->forgetAuthState();
        $this->postJson('/api/iamuser', [], $this->bearer($this->loginToken('admin1')))
            ->assertForbidden()->assertExactJson(['status' => false, 'message' => 'Unauthorized']);
    }

    public function test_request_reset_emails_a_front_end_link(): void
    {
        Mail::fake();
        $this->makeUser('user');

        $this->postJson('/api/auth/requestreset', ['email' => 'user1@example.com'])
            ->assertOk()
            ->assertExactJson(['status' => true, 'message' => 'Please check you email for reset link.']);

        $row = DB::table('password_resets')->where('email', 'user1@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame(60, strlen($row->token));

        Mail::assertSent(APIResetPassword::class, function (APIResetPassword $mail) use ($row) {
            return $mail->hasTo('user1@example.com')
                && $mail->reset->link === 'https://frontend.test/reset/'.$row->token
                && $mail->reset->appName === config('app.name');
        });
    }

    public function test_request_reset_for_an_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/requestreset', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertExactJson(['status' => false, 'message' => 'User not found or email not matched with login ID.']);

        Mail::assertNothingSent();
    }

    public function test_reset_email_renders(): void
    {
        $reset = (object) ['link' => 'https://frontend.test/reset/abc', 'appName' => 'JWT', 'appURL' => 'https://frontend.test'];

        $html = (new APIResetPassword($reset))->render();

        $this->assertStringContainsString('https://frontend.test/reset/abc', $html);
        $this->assertStringContainsString('Reset Password', $html);
    }

    public function test_reset_sets_a_new_password(): void
    {
        $this->makeUser('user');
        DB::table('password_resets')->insert(['email' => 'user1@example.com', 'token' => 'tok123', 'created_at' => now()]);

        $this->postJson('/api/auth/reset', ['token' => 'tok123', 'password' => 'newpass1'])
            ->assertOk()
            ->assertExactJson(['status' => true, 'message' => 'New password saved successfully.']);

        $this->assertDatabaseMissing('password_resets', ['token' => 'tok123']);
        $this->loginToken('user1', 'newpass1');
    }

    public function test_reset_rejects_expired_and_invalid_tokens(): void
    {
        $this->makeUser('user');
        DB::table('password_resets')->insert(['email' => 'user1@example.com', 'token' => 'old', 'created_at' => now()->subHours(2)]);

        $this->postJson('/api/auth/reset', ['token' => 'old', 'password' => 'newpass1'])
            ->assertOk()->assertExactJson(['status' => false, 'message' => 'Reset password link has expired.']);

        $this->postJson('/api/auth/reset', ['token' => 'nope', 'password' => 'newpass1'])
            ->assertOk()->assertExactJson(['status' => false, 'message' => 'Reset password link is invalid.']);

        $this->postJson('/api/auth/reset', [])->assertUnprocessable()->assertJsonValidationErrors(['token', 'password']);
    }
}
