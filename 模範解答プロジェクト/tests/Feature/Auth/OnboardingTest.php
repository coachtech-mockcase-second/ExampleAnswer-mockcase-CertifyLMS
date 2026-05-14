<?php

namespace Tests\Feature\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function freshInvitation(): Invitation
    {
        $admin = User::factory()->admin()->create();
        $invitedUser = User::factory()->invited()->create();

        return Invitation::factory()
            ->forUser($invitedUser)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);
    }

    private function signedShowUrl(Invitation $invitation): string
    {
        return app(InvitationTokenService::class)->generateUrl($invitation);
    }

    public function test_show_renders_form_with_valid_signed_url(): void
    {
        $invitation = $this->freshInvitation();

        $response = $this->get($this->signedShowUrl($invitation));

        $response->assertOk();
        $response->assertViewIs('auth.onboarding');
        $response->assertSee($invitation->email);
        $response->assertSee($invitation->role->label());
    }

    public function test_show_renders_invalid_view_for_tampered_signature(): void
    {
        $invitation = $this->freshInvitation();
        $url = $this->signedShowUrl($invitation);
        $tampered = preg_replace('/signature=[^&]+/', 'signature=tampered', $url);

        $response = $this->get($tampered);

        $response->assertOk();
        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_show_renders_invalid_view_for_expired_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);

        $url = URL::temporarySignedRoute(
            'onboarding.show',
            now()->addDay(),
            ['invitation' => $invitation->id],
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_show_renders_invalid_view_for_accepted_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->accepted()
            ->create(['invited_by_user_id' => $admin->id]);

        $url = $this->signedShowUrl($invitation);

        $response = $this->get($url);

        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_show_renders_invalid_view_when_user_status_not_invited(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        $response = $this->get($this->signedShowUrl($invitation));

        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_store_updates_existing_invited_user_to_active(): void
    {
        $invitation = $this->freshInvitation();
        $userBefore = $invitation->user;

        $postUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );

        $response = $this->post($postUrl, [
            'name' => '受講太郎',
            'bio' => 'よろしくお願いします',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertDatabaseHas('users', [
            'id' => $userBefore->id,
            'status' => UserStatus::Active->value,
            'name' => '受講太郎',
            'bio' => 'よろしくお願いします',
            'profile_setup_completed' => true,
        ]);
    }

    public function test_store_marks_invitation_accepted_and_auto_logs_in(): void
    {
        $invitation = $this->freshInvitation();

        $postUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );

        $this->post($postUrl, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertAuthenticatedAs($invitation->user);
        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

    public function test_store_does_not_create_new_user_row(): void
    {
        $invitation = $this->freshInvitation();
        $countBefore = User::count();

        $postUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );

        $this->post($postUrl, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertSame($countBefore, User::count());
    }

    public function test_store_rejects_short_password(): void
    {
        $invitation = $this->freshInvitation();

        $postUrl = URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );

        $response = $this->from($this->signedShowUrl($invitation))->post($postUrl, [
            'name' => '受講太郎',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'status' => UserStatus::Invited->value,
        ]);
    }
}
