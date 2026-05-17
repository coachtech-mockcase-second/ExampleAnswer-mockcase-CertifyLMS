<?php

declare(strict_types=1);

namespace Tests\Feature\Http\User;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者ユーザー詳細画面の面談回数手動付与 (`POST /admin/users/{user}/grant-meeting-quota`) の HTTP 統合テスト。
 *
 * MeetingQuota ドメインの AdminGrantQuotaAction に委譲し、
 * MeetingQuotaTransaction(admin_grant) が `granted_by_user_id` 付きで記録されることを担保する。
 */
class GrantMeetingQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        $plan = Plan::factory()->published()->create();

        return User::factory()->inProgress()->withPlan($plan)->create();
    }

    public function test_admin_can_grant_quota(): void
    {
        $admin = User::factory()->admin()->create();
        $student = $this->student();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.grantMeetingQuota', $student), [
                'amount' => 5,
                'reason' => 'トラブル補填',
            ]);

        $response->assertRedirect(route('admin.users.show', $student));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::AdminGrant->value,
            'amount' => 5,
            'granted_by_user_id' => $admin->id,
            'note' => 'トラブル補填',
        ]);
    }

    public function test_admin_can_grant_without_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $student = $this->student();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.grantMeetingQuota', $student), [
                'amount' => 2,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::AdminGrant->value,
            'amount' => 2,
            'granted_by_user_id' => $admin->id,
            'note' => null,
        ]);
    }

    public function test_returns_422_for_amount_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $student = $this->student();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.grantMeetingQuota', $student), [
                'amount' => 0,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_returns_422_for_amount_above_max(): void
    {
        $admin = User::factory()->admin()->create();
        $student = $this->student();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.grantMeetingQuota', $student), [
                'amount' => 101,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_returns_422_for_reason_longer_than_200(): void
    {
        $admin = User::factory()->admin()->create();
        $student = $this->student();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->post(route('admin.users.grantMeetingQuota', $student), [
                'amount' => 1,
                'reason' => str_repeat('あ', 201),
            ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_coach_cannot_grant_quota(): void
    {
        $coach = User::factory()->coach()->create();
        $student = $this->student();

        $response = $this->actingAs($coach)
            ->post(route('admin.users.grantMeetingQuota', $student), ['amount' => 1]);

        $response->assertForbidden();
    }

    public function test_student_cannot_grant_quota(): void
    {
        $caller = User::factory()->student()->create();
        $target = $this->student();

        $response = $this->actingAs($caller)
            ->post(route('admin.users.grantMeetingQuota', $target), ['amount' => 1]);

        $response->assertForbidden();
    }
}
