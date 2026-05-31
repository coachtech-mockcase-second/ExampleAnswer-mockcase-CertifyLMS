<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\User;
use App\Policies\ChapterViewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChapterViewPolicy の view 判定を検証する Unit テスト。
 * 受講生のみ + 親 Part の Certification を Learning または Passed で受講登録中であることを条件とする。
 * 復習用 (passed) も許可するが、未登録の coach / admin は不許可。
 */
class ChapterViewPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_learning_enrollment_can_view(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // Act
        $result = (new ChapterViewPolicy)->view($student, $chapter);

        // Assert
        $this->assertTrue($result, '受講中 (learning) 受講生は親 Certification の Chapter を閲覧できるはず');
    }

    public function test_student_without_enrollment_cannot_view(): void
    {
        // Arrange
        $chapter = Chapter::factory()->published()->create();
        $student = User::factory()->student()->create();

        // Act
        $result = (new ChapterViewPolicy)->view($student, $chapter);

        // Assert
        $this->assertFalse($result, '未受講の受講生は Chapter を閲覧できないはず');
    }

    public function test_coach_cannot_view(): void
    {
        // Arrange
        $chapter = Chapter::factory()->published()->create();
        $coach = User::factory()->coach()->create();

        // Act
        $result = (new ChapterViewPolicy)->view($coach, $chapter);

        // Assert
        $this->assertFalse($result, 'コーチは ChapterView では弾かれる (Section 閲覧は別 Policy)');
    }
}
