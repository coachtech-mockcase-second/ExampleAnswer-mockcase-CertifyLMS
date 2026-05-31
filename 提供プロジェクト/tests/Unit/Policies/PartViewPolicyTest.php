<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\User;
use App\Policies\PartViewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PartViewPolicy の view 判定を検証する Unit テスト。
 * 受講生のみ + Certification を Learning または Passed で受講登録中であることを条件とする。
 */
class PartViewPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_learning_enrollment_can_view(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->published()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // Act
        $result = (new PartViewPolicy)->view($student, $part);

        // Assert
        $this->assertTrue($result);
    }

    public function test_student_with_passed_enrollment_can_view_for_review(): void
    {
        // Arrange: passed (修了済) でも復習として閲覧可
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->published()->create();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student)->for($cert)->passed()->create();

        // Act
        $result = (new PartViewPolicy)->view($student, $part);

        // Assert
        $this->assertTrue($result, 'passed 受講生も Part を復習閲覧できるはず');
    }

    public function test_student_without_enrollment_cannot_view(): void
    {
        // Arrange
        $part = Part::factory()->published()->create();
        $student = User::factory()->student()->create();

        // Act
        $result = (new PartViewPolicy)->view($student, $part);

        // Assert
        $this->assertFalse($result);
    }

    public function test_admin_and_coach_cannot_view(): void
    {
        // Arrange
        $part = Part::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $policy = new PartViewPolicy;

        // Act & Assert
        $this->assertFalse($policy->view($admin, $part), 'admin は PartView 専用 Policy では弾かれる');
        $this->assertFalse($policy->view($coach, $part));
    }
}
