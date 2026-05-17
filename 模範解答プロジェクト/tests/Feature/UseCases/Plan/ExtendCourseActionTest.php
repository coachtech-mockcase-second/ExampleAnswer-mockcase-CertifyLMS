<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Enums\UserPlanLogEventType;
use App\Enums\UserStatus;
use App\Exceptions\Plan\PlanNotPublishedException;
use App\Exceptions\Plan\UserNotInProgressException;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\MeetingQuota\GrantInitialQuotaAction;
use App\UseCases\Plan\ExtendCourseAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ExtendCourseActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extends_plan_expiration_and_max_meetings_for_in_progress_user(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->state(['duration_days' => 30, 'default_meeting_quota' => 4])->create();
        $startedAt = now()->copy()->subDays(10);
        $user = User::factory()->inProgress()->withPlan($plan, $startedAt)->create();

        $originalExpires = $user->fresh()->plan_expires_at;

        $this->mock(GrantInitialQuotaAction::class, function (MockInterface $mock) use ($user, $admin) {
            $mock->shouldReceive('__invoke')
                ->once()
                ->withArgs(function ($u, $amount, $by, $reason) use ($user, $admin) {
                    return $u->is($user)
                        && $amount === 4
                        && $by !== null && $by->is($admin)
                        && is_string($reason);
                });
        });

        $result = app(ExtendCourseAction::class)($user, $plan, $admin, 'プラン更新');

        $this->assertSame(
            $originalExpires->copy()->addDays(30)->toDateTimeString(),
            $result->plan_expires_at->toDateTimeString(),
        );
        $this->assertSame($plan->default_meeting_quota + $plan->default_meeting_quota, $result->max_meetings);
    }

    public function test_records_renewed_user_plan_log(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->inProgress()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class, fn (MockInterface $mock) => $mock->shouldReceive('__invoke')->once());

        app(ExtendCourseAction::class)($user, $plan, $admin, 'プラン更新');

        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'event_type' => UserPlanLogEventType::Renewed->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => 'プラン更新',
        ]);
    }

    public function test_throws_when_user_is_graduated(): void
    {
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->graduated()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class);

        $this->expectException(UserNotInProgressException::class);
        app(ExtendCourseAction::class)($user, $plan, null);
    }

    public function test_throws_when_user_is_withdrawn(): void
    {
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->withdrawn()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class);

        $this->expectException(UserNotInProgressException::class);
        app(ExtendCourseAction::class)($user, $plan, null);
    }

    public function test_throws_when_plan_is_draft(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->inProgress()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class);

        $this->expectException(PlanNotPublishedException::class);
        app(ExtendCourseAction::class)($user, $plan, null);
    }

    public function test_throws_when_plan_is_archived(): void
    {
        $plan = Plan::factory()->archived()->create();
        $user = User::factory()->inProgress()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class);

        $this->expectException(PlanNotPublishedException::class);
        app(ExtendCourseAction::class)($user, $plan, null);
    }

    public function test_extends_from_now_when_plan_expires_at_is_null(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->state(['duration_days' => 30, 'default_meeting_quota' => 4])->create();
        $user = User::factory()->inProgress()->create([
            'plan_id' => $plan->id,
            'plan_started_at' => null,
            'plan_expires_at' => null,
            'max_meetings' => 0,
        ]);

        $this->mock(GrantInitialQuotaAction::class, fn (MockInterface $mock) => $mock->shouldReceive('__invoke')->once());

        $result = app(ExtendCourseAction::class)($user, $plan, $admin);

        $this->assertNotNull($result->plan_expires_at);
        $this->assertSame(4, $result->max_meetings);
    }

    public function test_user_status_remains_in_progress_after_extension(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->inProgress()->withPlan($plan)->create();

        $this->mock(GrantInitialQuotaAction::class, fn (MockInterface $mock) => $mock->shouldReceive('__invoke')->once());

        $result = app(ExtendCourseAction::class)($user, $plan, $admin);

        $this->assertSame(UserStatus::InProgress, $result->status);
    }
}
