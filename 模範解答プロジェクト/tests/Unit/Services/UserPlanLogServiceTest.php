<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserPlanLogEventType;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\Services\UserPlanLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPlanLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_inserts_user_plan_log_with_assigned_event(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->withPlan($plan)->create();

        $log = app(UserPlanLogService::class)->record(
            $user,
            $plan,
            UserPlanLogEventType::Assigned,
            $admin,
            'オンボーディング',
        );

        $this->assertInstanceOf(UserPlanLog::class, $log);
        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'event_type' => 'assigned',
            'changed_by_user_id' => $admin->id,
            'changed_reason' => 'オンボーディング',
        ]);
    }

    public function test_record_supports_all_event_types(): void
    {
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->withPlan($plan)->create();

        foreach ([
            UserPlanLogEventType::Assigned,
            UserPlanLogEventType::Renewed,
            UserPlanLogEventType::Canceled,
            UserPlanLogEventType::Expired,
        ] as $type) {
            $log = app(UserPlanLogService::class)->record($user, $plan, $type, null, 'test');

            $this->assertSame($type, $log->fresh()->event_type);
        }

        $this->assertSame(4, UserPlanLog::query()->count());
    }

    public function test_record_with_null_changed_by_is_system_event(): void
    {
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->withPlan($plan)->create();

        $log = app(UserPlanLogService::class)->record($user, $plan, UserPlanLogEventType::Expired, null, '期限満了');

        $this->assertNull($log->changed_by_user_id);
        $this->assertSame('期限満了', $log->changed_reason);
    }
}
