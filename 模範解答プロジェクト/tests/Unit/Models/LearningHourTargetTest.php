<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LearningHourTarget モデルのリレーション・Cast を検証する Unit テスト。
 * 1 リレーション (enrollment) + 1 cast (target_total_hours integer) を網羅する。
 * 受講登録ごとの目標総学習時間を保持するモデル。
 */
class LearningHourTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_owner_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $target = LearningHourTarget::factory()->forEnrollment($enrollment)->create();

        // Act
        $owner = $target->enrollment;

        // Assert
        $this->assertTrue($owner->is($enrollment));
    }

    public function test_target_total_hours_cast_returns_integer(): void
    {
        // Arrange
        $target = LearningHourTarget::factory()->hours(120)->create();

        // Act
        $fresh = $target->fresh();

        // Assert
        $this->assertIsInt($fresh->target_total_hours, 'target_total_hours は integer にキャストされるはず');
        $this->assertSame(120, $fresh->target_total_hours);
    }
}
