<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\InvitationStatus;
use App\Enums\MeetingQuotaTransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * オンボーディング画面(招待トークン検証 → 初回パスワード設定 + プロフィール入力)の Feature テスト。
 * 署名付き URL の検証、status 遷移(invited → in_progress)、Plan 期間反映、コーチの meeting_url 必須化、初期面談クォータ起票を担保する。
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $durationDays = 90, int $quota = 6): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => $durationDays,
            'default_meeting_quota' => $quota,
        ]);
    }

    private function freshInvitation(UserRole $role = UserRole::Student, ?Plan $plan = null): Invitation
    {
        $admin = User::factory()->admin()->create();
        $factory = User::factory()
            ->state(['role' => $role->value])
            ->invited();

        // 受講生のみ Plan 紐づけ(コーチは Plan を持たない)
        if ($role === UserRole::Student) {
            $plan ??= $this->plan();
            $factory = $factory->withPlan($plan);
        }

        $invitedUser = $factory->create();

        return Invitation::factory()
            ->forUser($invitedUser)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);
    }

    private function signedShowUrl(Invitation $invitation): string
    {
        return app(InvitationTokenService::class)->generateUrl($invitation);
    }

    private function postUrl(Invitation $invitation): string
    {
        return URL::temporarySignedRoute(
            'onboarding.store',
            $invitation->expires_at,
            ['invitation' => $invitation->id],
        );
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
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->withPlan($plan)->create();
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
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->withPlan($plan)->create();
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
        $plan = $this->plan();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::InProgress]);
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        $response = $this->get($this->signedShowUrl($invitation));

        $response->assertViewIs('auth.invitation-invalid');
    }

    public function test_store_updates_existing_invited_user_to_in_progress(): void
    {
        $invitation = $this->freshInvitation();
        $userBefore = $invitation->user;

        $response = $this->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'bio' => 'よろしくお願いします',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertDatabaseHas('users', [
            'id' => $userBefore->id,
            'status' => UserStatus::InProgress->value,
            'name' => '受講太郎',
            'bio' => 'よろしくお願いします',
            'profile_setup_completed' => true,
        ]);
    }

    public function test_store_sets_plan_period_from_plan_duration_days(): void
    {
        $plan = $this->plan(durationDays: 120);
        $invitation = $this->freshInvitation(plan: $plan);

        $this->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $user = $invitation->user->fresh();
        $this->assertNotNull($user->plan_started_at);
        $this->assertNotNull($user->plan_expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(120)->timestamp,
            $user->plan_expires_at->timestamp,
            5,
        );
    }

    public function test_store_marks_invitation_accepted_and_auto_logs_in(): void
    {
        $invitation = $this->freshInvitation();

        $this->post($this->postUrl($invitation), [
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

        $this->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertSame($countBefore, User::count());
    }

    public function test_store_records_meeting_quota_transaction_granted_initial(): void
    {
        $plan = $this->plan(quota: 9);
        $invitation = $this->freshInvitation(plan: $plan);

        $this->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $invitation->user_id,
            'type' => MeetingQuotaTransactionType::GrantedInitial->value,
            'amount' => 9,
        ]);
    }

    public function test_store_rejects_short_password(): void
    {
        $invitation = $this->freshInvitation();

        $response = $this->from($this->signedShowUrl($invitation))->post($this->postUrl($invitation), [
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

    public function test_store_rejects_mismatched_password_confirmation(): void
    {
        $invitation = $this->freshInvitation();

        $response = $this->from($this->signedShowUrl($invitation))->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'different-pass',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'status' => UserStatus::Invited->value,
        ]);
    }

    public function test_store_requires_meeting_url_for_coach_invitation(): void
    {
        $invitation = $this->freshInvitation(role: UserRole::Coach);

        $response = $this->from($this->signedShowUrl($invitation))->post($this->postUrl($invitation), [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertSessionHasErrors('meeting_url');
        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'status' => UserStatus::Invited->value,
            'meeting_url' => null,
        ]);
    }

    public function test_store_rejects_invalid_meeting_url_format_for_coach(): void
    {
        $invitation = $this->freshInvitation(role: UserRole::Coach);

        $response = $this->from($this->signedShowUrl($invitation))->post($this->postUrl($invitation), [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'meeting_url' => 'not-a-valid-url',
        ]);

        $response->assertSessionHasErrors('meeting_url');
    }

    public function test_store_saves_meeting_url_for_coach_onboarding(): void
    {
        $invitation = $this->freshInvitation(role: UserRole::Coach);

        $this->post($this->postUrl($invitation), [
            'name' => 'コーチ太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'status' => UserStatus::InProgress->value,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);
    }

    public function test_store_does_not_require_meeting_url_for_student_invitation(): void
    {
        $invitation = $this->freshInvitation(role: UserRole::Student);

        $response = $this->post($this->postUrl($invitation), [
            'name' => '受講太郎',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertDatabaseHas('users', [
            'id' => $invitation->user_id,
            'status' => UserStatus::InProgress->value,
            'meeting_url' => null,
        ]);
    }
}
