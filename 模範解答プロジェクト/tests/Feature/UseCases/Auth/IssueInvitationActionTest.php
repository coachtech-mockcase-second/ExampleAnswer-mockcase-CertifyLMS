<?php

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IssueInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_with_invited_status_and_invitation_with_7_days_expiry(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $invitation = app(IssueInvitationAction::class)('newbie@example.test', UserRole::Student, $admin);

        $this->assertDatabaseHas('users', [
            'email' => 'newbie@example.test',
            'status' => UserStatus::Invited->value,
            'role' => UserRole::Student->value,
        ]);
        $this->assertSame(InvitationStatus::Pending, $invitation->status);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $invitation->expires_at->timestamp,
            5,
        );
    }

    public function test_user_has_nullable_password_and_name_when_invited(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        app(IssueInvitationAction::class)('np@example.test', UserRole::Coach, $admin);

        $user = User::where('email', 'np@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNull($user->name);
    }

    public function test_throws_email_already_registered_for_active_user(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        User::factory()->create([
            'email' => 'taken@example.test',
            'status' => UserStatus::Active,
        ]);

        $this->expectException(EmailAlreadyRegisteredException::class);

        app(IssueInvitationAction::class)('taken@example.test', UserRole::Student, $admin);
    }

    public function test_throws_pending_already_exists_when_force_is_false(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create(['email' => 'dupe@example.test']);
        Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(PendingInvitationAlreadyExistsException::class);

        app(IssueInvitationAction::class)('dupe@example.test', UserRole::Student, $admin, force: false);
    }

    public function test_re_invite_with_force_revokes_old_pending_and_keeps_user_invited_without_status_log(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create(['email' => 'reinvite@example.test']);
        $oldInvitation = Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        $statusLogsBefore = $user->statusLogs()->count();

        $newInvitation = app(IssueInvitationAction::class)(
            'reinvite@example.test',
            UserRole::Student,
            $admin,
            force: true,
        );

        $this->assertSame(InvitationStatus::Revoked, $oldInvitation->fresh()->status);
        $this->assertSame(InvitationStatus::Pending, $newInvitation->status);
        $this->assertSame($user->id, $newInvitation->user_id);
        $this->assertSame(UserStatus::Invited, $user->fresh()->status);
        $this->assertSame($statusLogsBefore, $user->statusLogs()->count(), 'force re-invite では UserStatusLog を新規挿入しないはず');
    }

    public function test_dispatches_invitation_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        app(IssueInvitationAction::class)('mailtest@example.test', UserRole::Student, $admin);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('mailtest@example.test'));
    }

    public function test_inserts_user_status_log_with_invited_status_on_new_user_insert(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $invitation = app(IssueInvitationAction::class)('log@example.test', UserRole::Student, $admin);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $invitation->user_id,
            'status' => UserStatus::Invited->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => '新規招待',
        ]);
    }
}
