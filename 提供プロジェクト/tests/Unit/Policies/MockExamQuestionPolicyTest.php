<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;
use App\Policies\MockExamQuestionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MockExamQuestionPolicy の判定を検証する Unit テスト。
 * admin: 全 ability で true / coach: 担当資格の MockExam のみ true / student: 全 false を網羅する。
 */
class MockExamQuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_all_abilities(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        $question = MockExamQuestion::factory()->for($mockExam)->create();
        $policy = new MockExamQuestionPolicy;

        // Act & Assert
        $this->assertTrue($policy->viewAny($admin, $mockExam));
        $this->assertTrue($policy->view($admin, $question));
        $this->assertTrue($policy->create($admin, $mockExam));
        $this->assertTrue($policy->update($admin, $question));
        $this->assertTrue($policy->delete($admin, $question));
        $this->assertTrue($policy->reorder($admin, $mockExam));
    }

    public function test_coach_can_manage_only_assigned_certification_questions(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedExam = MockExam::factory()->forCertification($assignedCert)->published()->create();
        $otherExam = MockExam::factory()->forCertification($otherCert)->published()->create();
        $policy = new MockExamQuestionPolicy;

        // Act & Assert
        $this->assertTrue($policy->create($coach, $assignedExam));
        $this->assertFalse($policy->create($coach, $otherExam), '非担当資格の MockExam の問題は作成できないはず');
    }

    public function test_student_cannot_manage_questions(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $mockExam = MockExam::factory()->published()->create();
        $question = MockExamQuestion::factory()->for($mockExam)->create();
        $policy = new MockExamQuestionPolicy;

        // Act & Assert
        $this->assertFalse($policy->viewAny($student, $mockExam));
        $this->assertFalse($policy->update($student, $question));
        $this->assertFalse($policy->delete($student, $question));
    }
}
