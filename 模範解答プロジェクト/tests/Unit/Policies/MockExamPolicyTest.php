<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\User;
use App\Policies\MockExamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MockExamPolicyTest extends TestCase
{
    use RefreshDatabase;

    private MockExamPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new MockExamPolicy;
    }

    public function test_admin_can_view_any_mock_exam_via_canManage(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create();

        $this->assertTrue($this->policy->view($admin, $mockExam));
        $this->assertTrue($this->policy->update($admin, $mockExam));
        $this->assertTrue($this->policy->delete($admin, $mockExam));
    }

    public function test_assigned_coach_can_manage_mock_exam(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->assertTrue($this->policy->view($coach, $mockExam));
        $this->assertTrue($this->policy->update($coach, $mockExam));
    }

    public function test_unassigned_coach_cannot_manage_mock_exam(): void
    {
        $coach = User::factory()->coach()->create();
        $mockExam = MockExam::factory()->create();

        $this->assertFalse($this->policy->view($coach, $mockExam));
        $this->assertFalse($this->policy->update($coach, $mockExam));
        $this->assertFalse($this->policy->delete($coach, $mockExam));
    }

    public function test_learning_student_can_take_published_mock_exam(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();

        $this->assertTrue($this->policy->take($student, $mockExam));
    }

    public function test_passed_student_can_review_published_mock_exam(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->passed()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();

        $this->assertTrue($this->policy->take($student, $mockExam));
    }

    public function test_student_cannot_take_unpublished_mock_exam(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create(['is_published' => false]);

        $this->assertFalse($this->policy->take($student, $mockExam));
    }

    public function test_student_without_enrollment_cannot_take(): void
    {
        $student = User::factory()->student()->create();
        $mockExam = MockExam::factory()->published()->create();

        $this->assertFalse($this->policy->take($student, $mockExam));
    }

    public function test_admin_cannot_take_mock_exam(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->published()->create();

        $this->assertFalse($this->policy->take($admin, $mockExam));
    }
}
