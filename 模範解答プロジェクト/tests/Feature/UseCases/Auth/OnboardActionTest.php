<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidInvitationTokenException;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Auth\OnboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * オンボーディング受領ユースケース `OnboardAction` の業務ロジックを直接検証する Feature テスト。
 * status 遷移(invited → in_progress) / プロフィール永続化 / Plan 期間確定 / コーチ meeting_url 保存 /
 * 初期面談クォータ起票(受講生のみ) / Invitation accepted 化 / 自動ログイン / 例外パス(期限切れ・受領済み・ロール不整合)を網羅する。
 */
class OnboardActionTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $durationDays = 90, int $quota = 6): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => $durationDays,
            'default_meeting_quota' => $quota,
        ]);
    }

    /**
     * @return array{0: Invitation, 1: User}
     */
    private function setupInvitedUser(UserRole $role = UserRole::Student, ?Plan $plan = null): array
    {
        $admin = User::factory()->admin()->create();
        $factory = User::factory()
            ->state(['role' => $role->value])
            ->invited();

        // 受講生のみ Plan を紐づける(コーチは Plan を持たない)
        if ($role === UserRole::Student) {
            $plan ??= $this->plan();
            $factory = $factory->withPlan($plan);
        }

        $user = $factory->create();

        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        return [$invitation, $user];
    }

    public function test_transitions_user_to_in_progress_and_persists_profile(): void
    {
        [$invitation, $user] = $this->setupInvitedUser();

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'bio' => 'よろしくお願いします',
            'password' => 'secret-pass',
        ]);

        $user->refresh();
        $this->assertSame(UserStatus::InProgress, $user->status);
        $this->assertSame('受講太郎', $user->name);
        $this->assertSame('よろしくお願いします', $user->bio);
        $this->assertTrue($user->profile_setup_completed);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_marks_invitation_accepted(): void
    {
        [$invitation, ] = $this->setupInvitedUser();

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_sets_plan_started_at_and_expires_at_from_plan_duration(): void
    {
        $plan = $this->plan(durationDays: 180);
        [$invitation, $user] = $this->setupInvitedUser(plan: $plan);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);

        $user->refresh();
        $this->assertNotNull($user->plan_started_at);
        $this->assertNotNull($user->plan_expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(180)->timestamp,
            $user->plan_expires_at->timestamp,
            5,
        );
    }

    public function test_records_user_status_log_with_in_progress(): void
    {
        [$invitation, $user] = $this->setupInvitedUser();

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $user->id,
            'status' => UserStatus::InProgress->value,
            'changed_by_user_id' => $user->id,
            'changed_reason' => 'オンボーディング完了',
        ]);
    }

    public function test_records_granted_initial_meeting_quota_transaction(): void
    {
        $plan = $this->plan(quota: 8);
        [$invitation, $user] = $this->setupInvitedUser(plan: $plan);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::GrantedInitial->value,
            'amount' => 8,
            'note' => 'オンボーディング初期付与',
        ]);
    }

    public function test_saves_meeting_url_when_role_is_coach(): void
    {
        [$invitation, $user] = $this->setupInvitedUser(role: UserRole::Coach);

        app(OnboardAction::class)($invitation, [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $user->refresh();
        $this->assertSame('https://meet.google.com/abc-defg-hij', $user->meeting_url);
    }

    public function test_does_not_set_plan_period_when_role_is_coach(): void
    {
        [$invitation, $user] = $this->setupInvitedUser(role: UserRole::Coach);

        app(OnboardAction::class)($invitation, [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $user->refresh();
        // コーチは受講期間という業務概念を持たないため、Plan 関連カラムは NULL のまま維持される
        $this->assertNull($user->plan_id);
        $this->assertNull($user->plan_started_at);
        $this->assertNull($user->plan_expires_at);
        $this->assertSame(0, $user->max_meetings);
    }

    public function test_does_not_save_meeting_url_when_role_is_student(): void
    {
        [$invitation, $user] = $this->setupInvitedUser(role: UserRole::Student);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'meeting_url' => 'https://example.test/should-be-ignored',
        ]);

        $user->refresh();
        $this->assertNull($user->meeting_url);
    }

    public function test_does_not_record_granted_initial_transaction_for_coach(): void
    {
        [$invitation, $user] = $this->setupInvitedUser(role: UserRole::Coach);

        app(OnboardAction::class)($invitation, [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        // 面談クォータは受講生向けの仕組みのため、コーチには起票されない
        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_auto_logs_user_in_after_commit(): void
    {
        [$invitation, $user] = $this->setupInvitedUser();

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
    }

    public function test_throws_when_invitation_is_expired(): void
    {
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->withPlan($plan)->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->expiringAt(now()->subDay())
            ->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(InvalidInvitationTokenException::class);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);
    }

    public function test_throws_when_invitation_already_accepted(): void
    {
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->withPlan($plan)->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->accepted()
            ->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(InvalidInvitationTokenException::class);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);
    }

    public function test_throws_when_user_status_is_not_invited(): void
    {
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::InProgress]);
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(InvalidInvitationTokenException::class);

        app(OnboardAction::class)($invitation, [
            'name' => '受講太郎',
            'password' => 'secret-pass',
        ]);
    }
}
