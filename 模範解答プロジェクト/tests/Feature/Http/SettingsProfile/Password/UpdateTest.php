<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Password;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->put(route('settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-strong-pass-1',
                'password_confirmation' => 'new-strong-pass-1',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('new-strong-pass-1', $user->fresh()->password));
    }

    public function test_validation_fails_when_current_password_is_incorrect(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-strong-pass-1',
                'password_confirmation' => 'new-strong-pass-1',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHasErrors(['current_password'], null, 'updatePassword');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_validation_fails_when_new_password_is_too_short(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrors(['password'], null, 'updatePassword');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_validation_fails_when_password_confirmation_does_not_match(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-strong-pass-1',
                'password_confirmation' => 'different-pass',
            ]);

        $response->assertSessionHasErrors(['password'], null, 'updatePassword');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
