<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use App\Policies\EnrollmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicy の判定を検証する Unit テスト。
 * viewAny / view は EnrollmentPolicy::view 委譲。
 * create / update / delete / markAchieved / unmarkAchieved は student かつ goal->enrollment->user_id === user.id のみ。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_owner_can_create_goal_on_own_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act
        $result = $policy->create($student, $enrollment);

        // Assert
        $this->assertTrue($result, '受講生は自分の enrollment に goal を作成できるはず');
    }

    public function test_student_cannot_create_goal_on_others_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act
        $result = $policy->create($student, $otherEnrollment);

        // Assert
        $this->assertFalse($result);
    }

    public function test_student_owner_can_update_own_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act
        $result = $policy->update($student, $goal);

        // Assert
        $this->assertTrue($result);
    }

    public function test_student_cannot_update_others_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->for($other)->learning()->create();
        $otherGoal = EnrollmentGoal::factory()->for($otherEnrollment)->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act
        $result = $policy->update($student, $otherGoal);

        // Assert
        $this->assertFalse($result, '他人の goal は更新不可');
    }

    public function test_admin_cannot_create_goal_admin_not_a_student(): void
    {
        // Arrange: create ability は student ロール限定
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act
        $result = $policy->create($admin, $enrollment);

        // Assert
        $this->assertFalse($result, 'goal の create は student ロール限定 (admin でも不可)');
    }

    public function test_mark_achieved_inherits_owner_authorization(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();
        $policy = new EnrollmentGoalPolicy(new EnrollmentPolicy);

        // Act & Assert
        $this->assertTrue($policy->markAchieved($student, $goal));
        $this->assertTrue($policy->unmarkAchieved($student, $goal));
    }
}
