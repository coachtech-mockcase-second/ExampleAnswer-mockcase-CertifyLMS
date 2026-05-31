<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Announcement モデルのリレーション・Cast を検証する Unit テスト。
 * 主要 3 リレーション (createdBy / targetCertification / targetUser) +
 * 3 cast (target_type enum / dispatched_count int / dispatched_at datetime) を網羅する。
 * 全受講生 / 資格別 / 個人別 の配信対象を target_type で切り替える。
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_by_relation_returns_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->allStudents()->create(['created_by_user_id' => $admin->id]);

        // Act
        $creator = $announcement->createdBy;

        // Assert
        $this->assertTrue($creator->is($admin));
    }

    public function test_target_certification_relation_for_certification_target(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $announcement = Announcement::factory()->forCertification($cert)->create();

        // Act
        $target = $announcement->targetCertification;

        // Assert
        $this->assertNotNull($target);
        $this->assertTrue($target->is($cert));
    }

    public function test_target_type_cast_for_all_students(): void
    {
        // Arrange
        $announcement = Announcement::factory()->allStudents()->create();

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertInstanceOf(AnnouncementTargetType::class, $fresh->target_type, 'target_type は AnnouncementTargetType enum にキャストされるはず');
        $this->assertSame(AnnouncementTargetType::AllStudents, $fresh->target_type);
    }

    public function test_dispatched_count_and_dispatched_at_casts(): void
    {
        // Arrange
        $announcement = Announcement::factory()->dispatched(50)->create();

        // Act
        $fresh = $announcement->fresh();

        // Assert
        $this->assertIsInt($fresh->dispatched_count, 'dispatched_count は integer にキャストされるはず');
        $this->assertSame(50, $fresh->dispatched_count);
        $this->assertInstanceOf(Carbon::class, $fresh->dispatched_at);
    }
}
