<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserPlanLogEventType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\InvalidInvitationPlanException;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Auth\IssueInvitationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 招待発行ユースケース `IssueInvitationAction` の業務ロジックを直接検証する Feature テスト。
 * 新規 invited User 作成 / Plan カラム複写 / 既存 in_progress・graduated 不在検査 / 同 email pending の force 制御 /
 * UserStatusLog 記録 / UserPlanLog(assigned) 起票 / 招待メール送信を網羅する。
 */
class IssueInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $durationDays = 90, int $quota = 6): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => $durationDays,
            'default_meeting_quota' => $quota,
        ]);
    }

    public function test_creates_user_with_invited_status_and_invitation_with_7_days_expiry(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $invitation = app(IssueInvitationAction::class)(
            'newbie@example.test',
            UserRole::Student,
            $plan,
            $admin,
        );

        $this->assertDatabaseHas('users', [
            'email' => 'newbie@example.test',
            'status' => UserStatus::Invited->value,
            'role' => UserRole::Student->value,
            'plan_id' => $plan->id,
            'max_meetings' => $plan->default_meeting_quota,
            'plan_started_at' => null,
            'plan_expires_at' => null,
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

        // コーチ招待では Plan は渡さない
        app(IssueInvitationAction::class)('np@example.test', UserRole::Coach, null, $admin);

        $user = User::where('email', 'np@example.test')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNull($user->name);
    }

    public function test_coach_invitation_creates_user_without_plan_and_no_plan_log(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $invitation = app(IssueInvitationAction::class)('newcoach@example.test', UserRole::Coach, null, $admin);

        // コーチは Plan を持たない: plan_id NULL / max_meetings 0
        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'email' => 'newcoach@example.test',
            'role' => UserRole::Coach->value,
            'plan_id' => null,
            'max_meetings' => 0,
            'plan_started_at' => null,
            'plan_expires_at' => null,
        ]);
        // コーチ招待では UserPlanLog を起票しない
        $this->assertDatabaseMissing('user_plan_logs', [
            'user_id' => $invitation->user_id,
        ]);
    }

    public function test_throws_when_student_invitation_without_plan(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->expectException(InvalidInvitationPlanException::class);

        app(IssueInvitationAction::class)('noplan@example.test', UserRole::Student, null, $admin);
    }

    public function test_throws_when_coach_invitation_with_plan(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $this->expectException(InvalidInvitationPlanException::class);

        app(IssueInvitationAction::class)('coachwithplan@example.test', UserRole::Coach, $plan, $admin);
    }

    public function test_throws_email_already_registered_for_in_progress_user(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        User::factory()->create([
            'email' => 'taken@example.test',
            'status' => UserStatus::InProgress,
        ]);

        $this->expectException(EmailAlreadyRegisteredException::class);

        app(IssueInvitationAction::class)('taken@example.test', UserRole::Student, $plan, $admin);
    }

    public function test_throws_email_already_registered_for_graduated_user(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        User::factory()->create([
            'email' => 'grad@example.test',
            'status' => UserStatus::Graduated,
        ]);

        $this->expectException(EmailAlreadyRegisteredException::class);

        app(IssueInvitationAction::class)('grad@example.test', UserRole::Student, $plan, $admin);
    }

    public function test_throws_pending_already_exists_when_force_is_false(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $user = User::factory()->invited()->withPlan($plan)->create(['email' => 'dupe@example.test']);
        Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(PendingInvitationAlreadyExistsException::class);

        app(IssueInvitationAction::class)('dupe@example.test', UserRole::Student, $plan, $admin, force: false);
    }

    public function test_re_invite_with_force_revokes_old_pending_and_keeps_user_invited_without_status_log(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $user = User::factory()->invited()->withPlan($plan)->create(['email' => 'reinvite@example.test']);
        $oldInvitation = Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        $statusLogsBefore = $user->statusLogs()->count();

        $newInvitation = app(IssueInvitationAction::class)(
            'reinvite@example.test',
            UserRole::Student,
            $plan,
            $admin,
            force: true,
        );

        $this->assertSame(InvitationStatus::Revoked, $oldInvitation->fresh()->status);
        $this->assertSame(InvitationStatus::Pending, $newInvitation->status);
        $this->assertSame($user->id, $newInvitation->user_id);
        $this->assertSame(UserStatus::Invited, $user->fresh()->status);
        $this->assertSame($statusLogsBefore, $user->statusLogs()->count(), 'force re-invite では UserStatusLog を新規挿入しないはず');
    }

    public function test_re_invite_with_force_overwrites_plan_columns_on_existing_user(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $oldPlan = $this->plan(30, 3);
        $newPlan = $this->plan(180, 12);
        $user = User::factory()->invited()->withPlan($oldPlan)->create(['email' => 'replan@example.test']);
        Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        app(IssueInvitationAction::class)(
            'replan@example.test',
            UserRole::Student,
            $newPlan,
            $admin,
            force: true,
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan_id' => $newPlan->id,
            'max_meetings' => $newPlan->default_meeting_quota,
        ]);
    }

    public function test_dispatches_invitation_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        app(IssueInvitationAction::class)('mailtest@example.test', UserRole::Student, $plan, $admin);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('mailtest@example.test'));
    }

    public function test_inserts_user_status_log_with_invited_status_on_new_user_insert(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $invitation = app(IssueInvitationAction::class)('log@example.test', UserRole::Student, $plan, $admin);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $invitation->user_id,
            'status' => UserStatus::Invited->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => '新規招待',
        ]);
    }

    public function test_inserts_user_plan_log_with_assigned_event_type(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $invitation = app(IssueInvitationAction::class)('planlog@example.test', UserRole::Student, $plan, $admin);

        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $invitation->user_id,
            'plan_id' => $plan->id,
            'event_type' => UserPlanLogEventType::Assigned->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => '招待発行',
            'meeting_quota_initial' => $plan->default_meeting_quota,
        ]);
    }

    public function test_re_invite_with_force_does_not_duplicate_plan_log_when_plan_unchanged(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();
        $user = User::factory()->invited()->withPlan($plan)->create(['email' => 'samep@example.test']);
        Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        $planLogsBefore = $user->planLogs()->count();

        app(IssueInvitationAction::class)(
            'samep@example.test',
            UserRole::Student,
            $plan,
            $admin,
            force: true,
        );

        // 同 Plan の force=true 再送では UserPlanLog(assigned) を二重起票しない
        $this->assertSame($planLogsBefore, $user->planLogs()->count());
    }

    public function test_re_invite_with_force_records_plan_log_only_when_plan_changes(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $oldPlan = $this->plan(30, 3);
        $newPlan = $this->plan(180, 12);
        $user = User::factory()->invited()->withPlan($oldPlan)->create(['email' => 'switchp@example.test']);
        Invitation::factory()->forUser($user)->pending()->create(['invited_by_user_id' => $admin->id]);

        app(IssueInvitationAction::class)(
            'switchp@example.test',
            UserRole::Student,
            $newPlan,
            $admin,
            force: true,
        );

        // Plan 切替時は assigned ログを 1 件追加で起票する
        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $user->id,
            'plan_id' => $newPlan->id,
            'event_type' => UserPlanLogEventType::Assigned->value,
        ]);
    }
}
