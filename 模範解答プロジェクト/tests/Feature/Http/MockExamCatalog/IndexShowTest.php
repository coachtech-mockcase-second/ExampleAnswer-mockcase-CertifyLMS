<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExamCatalog;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_published_mock_exams_for_learning_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        $published = MockExam::factory()->forCertification($cert)->published()->create(['title' => '公開模試']);
        $draft = MockExam::factory()->forCertification($cert)->create(['title' => '下書き模試']);

        $response = $this->actingAs($student)
            ->get(route('mock-exam.catalog.index', $enrollment));

        $response->assertStatus(200);
        $response->assertSee('公開模試');
        $response->assertDontSee('下書き模試');
    }

    public function test_student_with_passed_enrollment_can_review_published_mock_exams(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->passed()->create();

        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();

        $response = $this->actingAs($student)
            ->get(route('mock-exam.catalog.show', ['enrollment' => $enrollment, 'mockExam' => $mockExam]));

        $response->assertStatus(200);
    }

    public function test_student_cannot_access_others_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->for($otherStudent)->learning()->create();

        $this->actingAs($student)
            ->get(route('mock-exam.catalog.index', $otherEnrollment))
            ->assertForbidden();
    }

    public function test_graduated_user_is_blocked_by_ensure_active_learning(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->passed()->create();

        $this->actingAs($student)
            ->get(route('mock-exam.catalog.index', $enrollment))
            ->assertForbidden();
    }

    public function test_show_returns_404_when_mock_exam_belongs_to_other_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // 別資格の模試
        $otherCert = Certification::factory()->published()->create();
        $otherMockExam = MockExam::factory()->forCertification($otherCert)->published()->create();

        $this->actingAs($student)
            ->get(route('mock-exam.catalog.show', ['enrollment' => $enrollment, 'mockExam' => $otherMockExam]))
            ->assertNotFound();
    }
}
