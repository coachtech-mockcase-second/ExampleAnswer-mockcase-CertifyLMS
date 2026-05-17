<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_returns_same_message_for_existing_and_non_existing_email(): void
    {
        Notification::fake();

        $existing = User::factory()->create([
            'email' => 'exists@example.test',
            'status' => UserStatus::InProgress,
        ]);

        $r1 = $this->post('/forgot-password', ['email' => 'exists@example.test']);
        $r2 = $this->post('/forgot-password', ['email' => 'ghost@example.test']);

        $r1->assertRedirect();
        $r2->assertRedirect();
        $r1->assertSessionHas('status');
        $r2->assertSessionHas('status');
        $this->assertSame($r1->getSession()->get('status'), $r2->getSession()->get('status'));

        Notification::assertSentTo($existing, ResetPasswordNotification::class);
    }

    public function test_reset_password_updates_hash_and_redirects(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.test',
            'password' => Hash::make('old-pass'),
            'status' => UserStatus::InProgress,
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'email' => $user->email,
            'password' => 'new-pass-123',
            'password_confirmation' => 'new-pass-123',
            'token' => $token,
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-pass-123', $user->fresh()->password));
    }
}
