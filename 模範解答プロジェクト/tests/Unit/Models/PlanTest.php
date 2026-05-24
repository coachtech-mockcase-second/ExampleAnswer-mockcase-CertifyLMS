<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 4 リレーション (createdBy / updatedBy / users / userPlanLogs) + 2 scope (published / ordered) +
 * 4 cast (status enum / duration_days int / default_meeting_quota int / sort_order int) を網羅する。
 * Plan は status による状態管理を採用するため SoftDelete は不採用。
 */
class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_by_relation_returns_admin_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['created_by_user_id' => $admin->id]);

        // Act
        $creator = $plan->createdBy;

        // Assert
        $this->assertTrue($creator->is($admin), 'created_by_user_id で関連付けた admin が createdBy で取得できるはず');
    }

    public function test_updated_by_relation_returns_admin_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['updated_by_user_id' => $admin->id]);

        // Act
        $updater = $plan->updatedBy;

        // Assert
        $this->assertTrue($updater->is($admin));
    }

    public function test_users_relation_returns_only_users_attached_to_plan(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->withPlan($plan)->create();
        User::factory()->student()->create(); // 別 plan の student、混入しないはず

        // Act
        $users = $plan->users;

        // Assert
        $this->assertCount(1, $users, '対象 plan に紐づく user のみが取得されるはず');
        $this->assertTrue($users->first()->is($student));
    }

    public function test_user_plan_logs_relation_returns_history(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->student()->withPlan($plan)->create();
        UserPlanLog::factory()->for($plan)->for($user)->create();
        UserPlanLog::factory()->for($plan)->for($user)->create();

        // Act
        $logs = $plan->userPlanLogs;

        // Assert
        $this->assertCount(2, $logs, '対象 plan に紐づく user_plan_logs を全件取得できるはず');
    }

    public function test_scope_published_filters_only_published_status(): void
    {
        // Arrange
        Plan::factory()->draft()->create();
        $published = Plan::factory()->published()->create();
        Plan::factory()->archived()->create();

        // Act
        $results = Plan::published()->get();

        // Assert
        $this->assertCount(1, $results, 'Published ステータスのみが scope で抽出されるはず');
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_ordered_sorts_by_sort_order_then_created_at_desc(): void
    {
        // Arrange
        $first = Plan::factory()->published()->create([
            'sort_order' => 1,
            'created_at' => now()->subDay(),
        ]);
        $second = Plan::factory()->published()->create([
            'sort_order' => 2,
            'created_at' => now(),
        ]);
        $third = Plan::factory()->published()->create([
            'sort_order' => 3,
            'created_at' => now()->addDay(),
        ]);

        // Act
        $results = Plan::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'sort_order 昇順で先頭は sort_order=1 のはず');
        $this->assertTrue($results->last()->is($third), 'sort_order 昇順で末尾は sort_order=3 のはず');
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $plan = Plan::factory()->create([
            'status' => PlanStatus::Published->value,
        ]);

        // Act
        $fresh = $plan->fresh();

        // Assert
        $this->assertInstanceOf(
            PlanStatus::class,
            $fresh->status,
            'status カラムは PlanStatus enum にキャストされるはず',
        );
        $this->assertSame(PlanStatus::Published, $fresh->status);
    }

    public function test_integer_casts_return_int(): void
    {
        // Arrange
        $plan = Plan::factory()->create([
            'duration_days' => '90',
            'default_meeting_quota' => '6',
            'sort_order' => '10',
        ]);

        // Act
        $fresh = $plan->fresh();

        // Assert
        $this->assertIsInt($fresh->duration_days, 'duration_days は integer にキャストされるはず');
        $this->assertIsInt($fresh->default_meeting_quota, 'default_meeting_quota は integer にキャストされるはず');
        $this->assertIsInt($fresh->sort_order, 'sort_order は integer にキャストされるはず');
        $this->assertSame(90, $fresh->duration_days);
        $this->assertSame(6, $fresh->default_meeting_quota);
        $this->assertSame(10, $fresh->sort_order);
    }
}
