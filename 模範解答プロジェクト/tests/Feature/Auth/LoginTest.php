<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_last_login_at_updated(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('secret-pass'),
            'status' => UserStatus::InProgress,
            'last_login_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'login@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(config('fortify.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invited_status_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'invited@example.test',
            'password' => Hash::make('secret-pass'),
            'status' => UserStatus::Invited,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'invited@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_withdrawn_status_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'gone@example.test',
            'password' => Hash::make('secret-pass'),
            'status' => UserStatus::Withdrawn,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'gone@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_password_returns_same_error_as_inactive_status(): void
    {
        User::factory()->create([
            'email' => 'live@example.test',
            'password' => Hash::make('correct-pass'),
            'status' => UserStatus::InProgress,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'live@example.test',
            'password' => 'wrong-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
