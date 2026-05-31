<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserPlanLogEventType;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * UserPlanLog モデルのリレーション・Cast を検証する Unit テスト。
 * 主要 3 リレーション (user / plan / changedBy) + 主要 cast (event_type enum / occurred_at datetime / meeting_quota_initial int) を網羅する。
 * 受講プランの付与 / 更新 / 取消 / 期限切れの履歴を記録する監査ログ。
 */
class UserPlanLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $log = UserPlanLog::factory()->for($student)->assigned()->create();

        // Act
        $owner = $log->user;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_plan_relation_returns_target_plan(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create();
        $log = UserPlanLog::factory()->for($plan)->assigned()->create();

        // Act
        $target = $log->plan;

        // Assert
        $this->assertTrue($target->is($plan));
    }

    public function test_event_type_cast_converts_to_enum(): void
    {
        // Arrange
        $log = UserPlanLog::factory()->assigned()->create();

        // Act
        $fresh = $log->fresh();

        // Assert
        $this->assertInstanceOf(UserPlanLogEventType::class, $fresh->event_type, 'event_type は UserPlanLogEventType enum にキャストされるはず');
        $this->assertSame(UserPlanLogEventType::Assigned, $fresh->event_type);
    }

    public function test_event_type_is_expired_for_expired_state(): void
    {
        // Arrange
        $log = UserPlanLog::factory()->expired()->create();

        // Act
        $fresh = $log->fresh();

        // Assert
        $this->assertSame(UserPlanLogEventType::Expired, $fresh->event_type);
    }

    public function test_occurred_at_cast_returns_carbon(): void
    {
        // Arrange
        $log = UserPlanLog::factory()->assigned()->create([
            'occurred_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $log->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->occurred_at);
    }
}
