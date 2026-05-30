<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EnrollmentGoal モデルのリレーション・Cast・isAchieved ヘルパ・displayOrder scope を検証する Unit テスト。
 * 1 リレーション (enrollment) + 2 cast (target_date date / achieved_at datetime) + isAchieved() + displayOrder scope を網羅する。
 */
class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_owner_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // Act
        $owner = $goal->enrollment;

        // Assert
        $this->assertTrue($owner->is($enrollment));
    }

    public function test_is_achieved_returns_true_when_achieved_at_present(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->achieved()->create();

        // Act
        $result = $goal->isAchieved();

        // Assert
        $this->assertTrue($result, 'achieved_at が記録された goal は達成済みと判定されるはず');
    }

    public function test_is_achieved_returns_false_when_achieved_at_null(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->create(['achieved_at' => null]);

        // Act
        $result = $goal->isAchieved();

        // Assert
        $this->assertFalse($result, 'achieved_at が null の goal は未達成と判定されるはず');
    }

    public function test_target_date_and_achieved_at_casts(): void
    {
        // Arrange
        $goal = EnrollmentGoal::factory()->create([
            'target_date' => '2026-12-01',
            'achieved_at' => '2026-11-20 10:00:00',
        ]);

        // Act
        $fresh = $goal->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->target_date);
        $this->assertSame('2026-12-01', $fresh->target_date->toDateString());
        $this->assertInstanceOf(Carbon::class, $fresh->achieved_at);
    }

    public function test_display_order_scope_orders_by_achievement_then_target_date_then_created_at(): void
    {
        // Arrange: 未達成(期日近 / 遠 / なし2件) + 達成済1件。期日なし2件は作成日をずらし第3キー(作成日降順)を検証する
        $enrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'near', 'target_date' => now()->addDays(5)->toDateString(), 'achieved_at' => null,
        ]);
        EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'far', 'target_date' => now()->addDays(30)->toDateString(), 'achieved_at' => null,
        ]);
        $noDateOlder = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'noDateOlder', 'target_date' => null, 'achieved_at' => null,
        ]);
        $noDateNewer = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => 'noDateNewer', 'target_date' => null, 'achieved_at' => null,
        ]);
        EnrollmentGoal::factory()->for($enrollment)->achieved()->create([
            'title' => 'achieved', 'target_date' => now()->addDay()->toDateString(),
        ]);
        // 期日なし 2 件の作成日前後を決定的にする(同一秒衝突の回避)
        $noDateOlder->forceFill(['created_at' => now()->subDays(2)])->save();
        $noDateNewer->forceFill(['created_at' => now()->subDay()])->save();

        // Act
        $ordered = EnrollmentGoal::query()->displayOrder()->pluck('title')->all();

        // Assert
        $this->assertSame(
            ['near', 'far', 'noDateNewer', 'noDateOlder', 'achieved'],
            $ordered,
            '未達成優先 → 期日が近い順 → 期日なしは作成日の新しい順 → 達成済は末尾、の順に並ぶはず',
        );
    }
}
