<?php

declare(strict_types=1);

namespace Tests\Feature\Http\User;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\UserPlanLogEventType;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者ユーザー詳細画面のプラン延長 (`POST /admin/users/{user}/extend-course`) の HTTP 統合テスト。
 *
 * Plan ドメインの ExtendCourseAction に委譲した結果として
 * (1) plan_expires_at の加算 / (2) max_meetings の加算 / (3) UserPlanLog(renewed) 記録 /
 * (4) MeetingQuotaTransaction(granted_initial) 起票 / (5) 認可 / バリデーションを担保する。
 */
class ExtendCourseTest extends TestCase
{
    use RefreshDatabase;

    private function plan(int $durationDays = 30, int $quota = 3): Plan
    {
        return Plan::factory()->published()->create([
            'duration_days' => $durationDays,
            'default_meeting_quota' => $quota,
        ]);
    }

    public function test_admin_can_extend_in_progress_student_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $originalPlan = $this->plan(90, 6);
        $student = User::factory()->inProgress()->withPlan($originalPlan)->create();
        $originalExpiresAt = $student->plan_expires_at;

        $extensionPlan = $this->plan(30, 3);

        $response = $this->actingAs($admin)->post(route('admin.users.extendCourse', $student), [
            'plan_id' => $extensionPlan->id,
        ]);

        $response->assertRedirect(route('admin.users.show', $student));
        $response->assertSessionHas('success');

        $fresh = $student->fresh();
        $this->assertSame(
            $originalExpiresAt->copy()->addDays(30)->timestamp,
            $fresh->plan_expires_at->timestamp,
            'plan_expires_at は extensionPlan.duration_days 日加算される',
        );
        $this->assertSame(6 + 3, $fresh->max_meetings, 'max_meetings は extensionPlan.default_meeting_quota 加算される');
    }

    public function test_inserts_user_plan_log_with_renewed_event_type(): void
    {
        $admin = User::factory()->admin()->create();
        $originalPlan = $this->plan(90, 6);
        $student = User::factory()->inProgress()->withPlan($originalPlan)->create();
        $extensionPlan = $this->plan(60, 4);

        $this->actingAs($admin)
            ->post(route('admin.users.extendCourse', $student), ['plan_id' => $extensionPlan->id])
            ->assertRedirect();

        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $student->id,
            'plan_id' => $extensionPlan->id,
            'event_type' => UserPlanLogEventType::Renewed->value,
            'changed_by_user_id' => $admin->id,
        ]);
    }

    public function test_inserts_meeting_quota_transaction_granted_initial(): void
    {
        $admin = User::factory()->admin()->create();
        $originalPlan = $this->plan(90, 6);
        $student = User::factory()->inProgress()->withPlan($originalPlan)->create();
        $extensionPlan = $this->plan(60, 4);

        $this->actingAs($admin)
            ->post(route('admin.users.extendCourse', $student), ['plan_id' => $extensionPlan->id])
            ->assertRedirect();

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::GrantedInitial->value,
            'amount' => 4,
        ]);
    }

    public function test_returns_422_for_missing_plan_id(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->inProgress()->withPlan($this->plan())->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.extendCourse', $student), []);

        $response->assertSessionHasErrors('plan_id');
    }

    public function test_returns_422_for_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->inProgress()->withPlan($this->plan())->create();
        $draft = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.extendCourse', $student), ['plan_id' => $draft->id]);

        $response->assertSessionHasErrors('plan_id');
    }

    public function test_returns_422_for_nonexistent_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->inProgress()->withPlan($this->plan())->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.extendCourse', $student), [
                'plan_id' => '01HXYZ0000000000000000PLAN',
            ]);

        $response->assertSessionHasErrors('plan_id');
    }

    public function test_coach_cannot_extend_course(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->inProgress()->withPlan($this->plan())->create();
        $extension = $this->plan();

        $response = $this->actingAs($coach)
            ->post(route('admin.users.extendCourse', $student), ['plan_id' => $extension->id]);

        $response->assertForbidden();
    }

    public function test_student_cannot_extend_course(): void
    {
        $caller = User::factory()->student()->create();
        $target = User::factory()->inProgress()->withPlan($this->plan())->create();
        $extension = $this->plan();

        $response = $this->actingAs($caller)
            ->post(route('admin.users.extendCourse', $target), ['plan_id' => $extension->id]);

        $response->assertForbidden();
    }
}
