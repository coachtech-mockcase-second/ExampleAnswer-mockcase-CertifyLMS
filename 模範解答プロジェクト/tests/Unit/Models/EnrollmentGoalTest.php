<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EnrollmentGoal モデルのリレーション・Cast・isAchieved ヘルパを検証する Unit テスト。
 * 1 リレーション (enrollment) + 2 cast (target_date date / achieved_at datetime) + isAchieved() を網羅する。
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
}
