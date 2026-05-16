<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvitationNotPendingException;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\RevokeInvitationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevokeInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingInvitation(?User $admin = null): Invitation
    {
        $admin ??= User::factory()->admin()->create();
        $user = User::factory()->invited()->create();

        return Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);
    }

    public function test_revokes_pending_invitation_and_cascade_withdraws_user(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = $this->makePendingInvitation($admin);
        $userId = $invitation->user_id;

        app(RevokeInvitationAction::class)($invitation, $admin);

        $this->assertSame(InvitationStatus::Revoked, $invitation->fresh()->status);
        $user = User::withTrashed()->find($userId);
        $this->assertSame(UserStatus::Withdrawn, $user->status);
        $this->assertTrue($user->trashed());
    }

    public function test_revoke_with_cascade_false_keeps_user_invited(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = $this->makePendingInvitation($admin);
        $userId = $invitation->user_id;
        $statusLogsBefore = $invitation->user->statusLogs()->count();

        app(RevokeInvitationAction::class)($invitation, admin: null, cascadeWithdrawUser: false);

        $this->assertSame(InvitationStatus::Revoked, $invitation->fresh()->status);
        $user = User::find($userId);
        $this->assertSame(UserStatus::Invited, $user->status);
        $this->assertFalse($user->trashed());
        $this->assertSame($statusLogsBefore, $user->statusLogs()->count());
    }

    public function test_throws_invitation_not_pending_for_accepted_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $accepted = Invitation::factory()
            ->forUser($user)
            ->accepted()
            ->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(InvitationNotPendingException::class);

        app(RevokeInvitationAction::class)($accepted, $admin);
    }

    public function test_cascade_withdraw_renames_email_and_soft_deletes(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = $this->makePendingInvitation($admin);
        $originalEmail = $invitation->user->email;

        app(RevokeInvitationAction::class)($invitation, $admin);

        $user = User::withTrashed()->find($invitation->user_id);
        $this->assertNotSame($originalEmail, $user->email);
        $this->assertStringContainsString('@deleted.invalid', $user->email);
        $this->assertTrue($user->trashed());
    }

    public function test_inserts_user_status_log_with_admin_actor_on_cascade(): void
    {
        $admin = User::factory()->admin()->create();
        $invitation = $this->makePendingInvitation($admin);

        app(RevokeInvitationAction::class)($invitation, $admin);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $invitation->user_id,
            'status' => UserStatus::Withdrawn->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => '招待取消',
        ]);
    }

    public function test_inserts_user_status_log_with_null_actor_when_admin_is_null(): void
    {
        $invitation = $this->makePendingInvitation();

        app(RevokeInvitationAction::class)($invitation, admin: null, cascadeWithdrawUser: true);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $invitation->user_id,
            'status' => UserStatus::Withdrawn->value,
            'changed_by_user_id' => null,
            'changed_reason' => '招待取消',
        ]);
    }
}
