<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_load(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="username"', false);
        $this->get('/register')->assertOk()->assertSee('name="role"', false);
        $this->get('/password/reset')->assertOk()->assertSee('name="email"', false);
    }

    public function test_home_needs_a_session(): void
    {
        $this->get('/home')->assertRedirect('/login');
        $this->get('/iamadmin')->assertRedirect('/login');
    }

    public function test_login_with_username_and_logout(): void
    {
        Pengguna::factory()->create(['username' => 'siti', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['username' => 'siti', 'password' => 'secret123'])->assertRedirect('/home');
        $this->assertAuthenticated();

        $this->get('/home')->assertOk()->assertSee('You are logged in!');
        $this->get('/login')->assertRedirect('/home');

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_with_a_wrong_password_fails(): void
    {
        Pengguna::factory()->create(['username' => 'siti', 'password' => Hash::make('secret123')]);

        $this->from('/login')->post('/login', ['username' => 'siti', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_register_form_creates_an_account(): void
    {
        $this->post('/register', [
            'name' => 'Ah Kow',
            'username' => 'ahkow',
            'email' => 'ahkow@example.com',
            'role' => 'user',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect('/home');

        $this->assertAuthenticated();
        $this->assertSame('user', Pengguna::find('ahkow')->role);
    }

    public function test_role_pages(): void
    {
        $admin = Pengguna::factory()->admin()->create();
        $user = Pengguna::factory()->create();

        $this->actingAs($admin)->get('/iamadmin')->assertOk()->assertExactJson(['data' => 'You are admin!']);
        $this->actingAs($admin)->get('/iamuser')->assertForbidden();
        $this->actingAs($user)->get('/iamuser')->assertOk()->assertExactJson(['data' => 'You are user!']);
        $this->actingAs($user)->get('/iamadmin')->assertForbidden();
    }
}
