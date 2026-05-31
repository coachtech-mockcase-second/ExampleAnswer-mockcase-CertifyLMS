<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExamSession;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MockExamSessionMonitorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        MockExamSession::factory()->count(3)->graded()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.mock-exam-sessions.index'));

        $response->assertOk();
    }

    public function test_coach_sees_only_sessions_of_assigned_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $assignedCert = Certification::factory()->published()->create();
        $assignedCert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $otherCert = Certification::factory()->published()->create();

        $assignedMockExam = MockExam::factory()->forCertification($assignedCert)->published()->create();
        $assignedEnrollment = Enrollment::factory()->for($assignedCert)->learning()->create();
        $assignedSession = MockExamSession::factory()
            ->forMockExam($assignedMockExam)
            ->forEnrollment($assignedEnrollment)
            ->graded(true, 8, 10)
            ->create();

        $otherMockExam = MockExam::factory()->forCertification($otherCert)->published()->create();
        $otherEnrollment = Enrollment::factory()->for($otherCert)->learning()->create();
        $otherSession = MockExamSession::factory()
            ->forMockExam($otherMockExam)
            ->forEnrollment($otherEnrollment)
            ->graded(true, 8, 10)
            ->create();

        $this->actingAs($coach)
            ->get(route('admin.mock-exam-sessions.show', $assignedSession))
            ->assertOk();

        $this->actingAs($coach)
            ->get(route('admin.mock-exam-sessions.show', $otherSession))
            ->assertForbidden();
    }

    public function test_student_cannot_access_admin_session_views(): void
    {
        $student = User::factory()->student()->create();
        $session = MockExamSession::factory()->graded()->create();

        $this->actingAs($student)
            ->get(route('admin.mock-exam-sessions.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.mock-exam-sessions.show', $session))
            ->assertForbidden();
    }
}
