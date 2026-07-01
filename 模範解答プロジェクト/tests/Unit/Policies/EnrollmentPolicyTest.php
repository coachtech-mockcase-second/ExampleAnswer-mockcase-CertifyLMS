<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\EnrollmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentPolicy の判定を検証する Unit テスト。
 * view: admin 全可 / student 本人 / coach 担当資格のみ。
 * 状態に依存する ability (updateExamDate, fail, resume, receiveCertificate, delete) を網羅する。
 */
class EnrollmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_role_branching(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $other = User::factory()->student()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->view($admin, $enrollment));
        $this->assertTrue($policy->view($student, $enrollment));
        $this->assertFalse($policy->view($other, $enrollment), '他人の enrollment は view 不可');
    }

    public function test_coach_can_view_only_assigned_certification(): void
    {
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
        $assignedEnrollment = Enrollment::factory()->for($assignedCert)->learning()->create();
        $otherEnrollment = Enrollment::factory()->for($otherCert)->learning()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->view($coach, $assignedEnrollment));
        $this->assertFalse($policy->view($coach, $otherEnrollment));
    }

    public function test_admin_can_update_exam_date_only_for_non_passed(): void
    {
        $admin = User::factory()->admin()->create();
        $learning = Enrollment::factory()->learning()->create();
        $passed = Enrollment::factory()->passed()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->updateExamDate($admin, $learning));
        $this->assertFalse($policy->updateExamDate($admin, $passed), 'passed enrollment の exam_date は変更不可');
    }

    public function test_student_can_update_own_exam_date_only_when_not_passed(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $learning = Enrollment::factory()->for($student)->learning()->create();
        $passed = Enrollment::factory()->for($student)->passed()->create();
        $othersLearning = Enrollment::factory()->for($other)->learning()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->updateExamDate($student, $learning), '本人 + learning は変更可');
        $this->assertFalse($policy->updateExamDate($student, $passed), 'passed は本人でも変更不可');
        $this->assertFalse($policy->updateExamDate($student, $othersLearning), '他人の enrollment は変更不可');
    }

    public function test_admin_can_fail_only_learning_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $learning = Enrollment::factory()->learning()->create();
        $passed = Enrollment::factory()->passed()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->fail($admin, $learning));
        $this->assertFalse($policy->fail($admin, $passed));
    }

    public function test_resume_only_for_failed_state(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $failed = Enrollment::factory()->for($student)->failed()->create();
        $learning = Enrollment::factory()->for($student)->learning()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->resume($admin, $failed));
        $this->assertTrue($policy->resume($student, $failed));
        $this->assertFalse($policy->resume($admin, $learning), 'failed 以外は resume 不可');
    }

    public function test_receive_certificate_only_for_owner_learning(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $learning = Enrollment::factory()->for($student)->learning()->create();
        $passed = Enrollment::factory()->for($student)->passed()->create();
        $policy = new EnrollmentPolicy;

        $this->assertTrue($policy->receiveCertificate($student, $learning));
        $this->assertFalse($policy->receiveCertificate($student, $passed), 'passed enrollment への再発行は不可');
        $this->assertFalse($policy->receiveCertificate($other, $learning), '他人の enrollment では受け取り不可');
    }
}
