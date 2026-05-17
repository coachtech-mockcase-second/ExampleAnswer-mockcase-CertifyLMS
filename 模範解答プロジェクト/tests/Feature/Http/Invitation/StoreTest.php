<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\InvitationMail;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 管理者の招待発行 HTTP エンドポイント(`POST /admin/invitations`)の Feature テスト。
 * 認可(admin のみ) / FormRequest バリデーション / Plan 紐づけ + 招待メール送信までの統合動作を担保する。
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $durationDays = 90, int $quota = 6): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => $durationDays,
            'default_meeting_quota' => $quota,
        ]);
    }

    public function test_admin_can_issue_invitation_for_coach_role_without_plan(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        // コーチ招待では plan_id を送らない
        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newcoach@example.test',
            'role' => UserRole::Coach->value,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'newcoach@example.test',
            'role' => UserRole::Coach->value,
            'status' => UserStatus::Invited->value,
            'plan_id' => null,
            'max_meetings' => 0,
        ]);
        $this->assertDatabaseHas('invitations', [
            'email' => 'newcoach@example.test',
            'status' => InvitationStatus::Pending->value,
        ]);

        Mail::assertSent(InvitationMail::class);
    }

    public function test_coach_invitation_rejects_plan_id_with_422(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'coachwithplan@example.test',
                'role' => UserRole::Coach->value,
                'plan_id' => $plan->id,
            ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('users', ['email' => 'coachwithplan@example.test']);
        Mail::assertNothingSent();
    }

    public function test_admin_can_issue_invitation_for_student_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan(quota: 12);

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newstudent@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => $plan->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newstudent@example.test',
            'role' => UserRole::Student->value,
            'status' => UserStatus::Invited->value,
            'plan_id' => $plan->id,
            'max_meetings' => 12,
        ]);
    }

    public function test_cannot_issue_invitation_for_admin_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'wanna-be-admin@example.test',
                'role' => UserRole::Admin->value,
                'plan_id' => $plan->id,
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'wanna-be-admin@example.test',
        ]);
        Mail::assertNothingSent();
    }

    public function test_plan_id_is_required(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'noplan@example.test',
                'role' => UserRole::Student->value,
            ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('users', ['email' => 'noplan@example.test']);
        Mail::assertNothingSent();
    }

    public function test_plan_id_must_exist(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'badplan@example.test',
                'role' => UserRole::Student->value,
                'plan_id' => '01HXYZ0000000000000000PLAN',
            ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('users', ['email' => 'badplan@example.test']);
        Mail::assertNothingSent();
    }

    public function test_draft_plan_id_is_rejected(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'draft@example.test',
                'role' => UserRole::Student->value,
                'plan_id' => $draftPlan->id,
            ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('users', ['email' => 'draft@example.test']);
        Mail::assertNothingSent();
    }

    public function test_archived_plan_id_is_rejected(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $archivedPlan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'arch@example.test',
                'role' => UserRole::Student->value,
                'plan_id' => $archivedPlan->id,
            ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertDatabaseMissing('users', ['email' => 'arch@example.test']);
        Mail::assertNothingSent();
    }

    public function test_dispatches_invitation_mail_and_inserts_status_log(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $plan = $this->plan();

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => $plan->id,
        ])->assertRedirect();

        $user = User::where('email', 'newbie@example.test')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $user->id,
            'changed_by_user_id' => $admin->id,
            'from_status' => UserStatus::Invited->value,
            'to_status' => UserStatus::Invited->value,
            'changed_reason' => '新規招待',
        ]);

        Mail::assertSent(InvitationMail::class);
    }

    public function test_coach_cannot_issue_invitation(): void
    {
        Mail::fake();
        $coach = User::factory()->coach()->create();
        $plan = $this->plan();

        $response = $this->actingAs($coach)->post(route('admin.invitations.store'), [
            'email' => 'a@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => $plan->id,
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
