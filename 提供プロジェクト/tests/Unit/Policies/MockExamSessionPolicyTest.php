<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use App\Policies\MockExamSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MockExamSessionPolicy の判定を検証する Unit テスト。
 * admin: 全可 / coach: 担当資格のみ view / student: 本人のみ操作可 を網羅する。
 */
class MockExamSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_operate_only_own_session(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($student)->inProgress()->create();
        $policy = new MockExamSessionPolicy;

        $this->assertTrue($policy->view($student, $session));
        $this->assertTrue($policy->start($student, $session));
        $this->assertTrue($policy->saveAnswer($student, $session));
        $this->assertTrue($policy->submit($student, $session));
        $this->assertTrue($policy->cancel($student, $session));
        $this->assertFalse($policy->submit($other, $session), '他人の session は操作不可');
    }

    public function test_admin_can_view_any_session(): void
    {
        $admin = User::factory()->admin()->create();
        $session = MockExamSession::factory()->inProgress()->create();
        $policy = new MockExamSessionPolicy;

        $this->assertTrue($policy->view($admin, $session));
        $this->assertTrue($policy->viewAdmin($admin));
    }

    public function test_coach_can_view_assigned_certification_session_only(): void
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
        $assignedSession = MockExamSession::factory()
            ->forMockExam(MockExam::factory()->forCertification($assignedCert)->published()->create())
            ->inProgress()->create();
        $otherSession = MockExamSession::factory()
            ->forMockExam(MockExam::factory()->forCertification($otherCert)->published()->create())
            ->inProgress()->create();
        $policy = new MockExamSessionPolicy;

        $this->assertTrue($policy->view($coach, $assignedSession));
        $this->assertFalse($policy->view($coach, $otherSession));
    }

    public function test_coach_cannot_operate_session(): void
    {
        $coach = User::factory()->coach()->create();
        $session = MockExamSession::factory()->inProgress()->create();
        $policy = new MockExamSessionPolicy;

        $this->assertFalse($policy->start($coach, $session));
        $this->assertFalse($policy->submit($coach, $session));
    }
}
