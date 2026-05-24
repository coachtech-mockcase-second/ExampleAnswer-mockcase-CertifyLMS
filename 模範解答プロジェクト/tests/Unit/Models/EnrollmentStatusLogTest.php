<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * EnrollmentStatusLog モデルのリレーション・Cast を検証する Unit テスト。
 * 2 リレーション (enrollment / changedBy) + 3 cast (from_status / to_status enum / changed_at datetime) を網羅する。
 * 受講登録の状態遷移を監査ログとして記録するモデル。
 */
class EnrollmentStatusLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_target_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $log = EnrollmentStatusLog::factory()->for($enrollment)->create();

        // Act
        $target = $log->enrollment;

        // Assert
        $this->assertTrue($target->is($enrollment));
    }

    public function test_changed_by_relation_returns_actor_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $log = EnrollmentStatusLog::factory()->for($admin, 'changedBy')->create();

        // Act
        $actor = $log->changedBy;

        // Assert
        $this->assertTrue($actor->is($admin), 'changed_by_user_id で関連付けた actor が取得できるはず');
    }

    public function test_status_casts_convert_to_enum(): void
    {
        // Arrange
        $log = EnrollmentStatusLog::factory()->create([
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Passed->value,
        ]);

        // Act
        $fresh = $log->fresh();

        // Assert
        $this->assertInstanceOf(EnrollmentStatus::class, $fresh->from_status, 'from_status は EnrollmentStatus enum にキャストされるはず');
        $this->assertSame(EnrollmentStatus::Learning, $fresh->from_status);
        $this->assertSame(EnrollmentStatus::Passed, $fresh->to_status);
    }

    public function test_changed_at_cast_returns_carbon(): void
    {
        // Arrange
        $log = EnrollmentStatusLog::factory()->create([
            'changed_at' => '2026-05-20 16:00:00',
        ]);

        // Act
        $fresh = $log->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->changed_at);
    }
}
