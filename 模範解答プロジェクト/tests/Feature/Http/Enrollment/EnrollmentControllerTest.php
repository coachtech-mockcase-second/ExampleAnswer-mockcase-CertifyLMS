<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講生向け EnrollmentController の HTTP 統合テスト。
 * 認可漏れ / FormRequest バリデーション失敗 / 代表的な正常系を網羅する。
 */
class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_student_enrollments_only(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $ownEnrollment = Enrollment::factory()->for($student)->create();
        $otherEnrollment = Enrollment::factory()->create();

        $response = $this->actingAs($student)->get(route('enrollments.index'));

        $response->assertOk();
        $response->assertViewIs('enrollment.index');
        $response->assertViewHas('enrollments', function ($enrollments) use ($ownEnrollment, $otherEnrollment) {
            return $enrollments->pluck('id')->contains($ownEnrollment->id)
                && ! $enrollments->pluck('id')->contains($otherEnrollment->id);
        });
    }

    public function test_show_allows_owner_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $response = $this->actingAs($student)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertViewIs('enrollment.show');
    }

    public function test_show_forbids_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $response = $this->actingAs($student)->get(route('enrollments.show', $otherEnrollment));

        $response->assertForbidden();
    }

    public function test_store_creates_enrollment_for_published_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('enrollments.store'), [
            'certification_id' => $certification->id,
            'exam_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'status' => EnrollmentStatus::Learning->value,
            'current_term' => 'basic_learning',
        ]);
        $this->assertDatabaseHas('enrollment_status_logs', [
            'changed_reason' => '新規登録',
            'changed_by_user_id' => $student->id,
        ]);
    }

    public function test_store_returns_404_for_draft_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.store'), [
            'certification_id' => $certification->id,
        ]);

        // FormRequest の exists ルールで弾かれる
        $this->assertContains($response->status(), [404, 422]);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_store_returns_409_for_duplicate_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.store'), [
            'certification_id' => $certification->id,
        ]);

        $this->assertSame(409, $response->status());
    }

    public function test_store_returns_422_when_exam_date_is_today_or_before(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.store'), [
            'certification_id' => $certification->id,
            'exam_date' => now()->toDateString(),
        ]);

        $this->assertSame(422, $response->status());
    }

    public function test_store_accepts_nullable_exam_date(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('enrollments.store'), [
            'certification_id' => $certification->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'exam_date' => null,
        ]);
    }

    public function test_graduated_student_cannot_access_enrollment_routes(): void
    {
        $graduated = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($graduated)->get(route('enrollments.index'));

        // active-learning Middleware で 403
        $response->assertForbidden();
    }

    public function test_coach_cannot_use_student_routes(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('enrollments.index'));

        // role:student Middleware で 403
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('enrollments.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_destroy_soft_deletes_own_learning_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));

        $response->assertRedirect(route('enrollments.index'));
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
    }

    public function test_destroy_rejects_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($student)->deleteJson(route('enrollments.destroy', $otherEnrollment));

        $response->assertForbidden();
    }

    public function test_destroy_rejects_passed_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();

        $response = $this->actingAs($student)->deleteJson(route('enrollments.destroy', $enrollment));

        // Policy::delete で status=Learning に絞っているため 403
        $response->assertForbidden();
    }

    public function test_resume_transitions_failed_to_learning_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->failed()->create();

        $response = $this->actingAs($student)->post(route('enrollments.resume', $enrollment));

        $response->assertRedirect();
        $this->assertSame(EnrollmentStatus::Learning, $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'from_status' => EnrollmentStatus::Failed->value,
            'to_status' => EnrollmentStatus::Learning->value,
            'changed_by_user_id' => $student->id,
            'changed_reason' => '再挑戦',
        ]);
    }

    public function test_resume_rejects_learning_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.resume', $enrollment));

        // Policy::resume で status=Failed に絞っているため 403
        $response->assertForbidden();
    }

    public function test_receive_certificate_succeeds_when_eligible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $exam = MockExam::factory()->for($certification)->create(['is_published' => true]);
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['pass' => true]);

        $response = $this->actingAs($student)->post(route('enrollments.receiveCertificate', $enrollment));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertSame(EnrollmentStatus::Passed, $enrollment->fresh()->status);
        $this->assertDatabaseHas('certificates', ['enrollment_id' => $enrollment->id]);
    }

    public function test_receive_certificate_returns_409_when_not_eligible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        // 公開模試 0 件で eligibility false

        $response = $this->actingAs($student)->postJson(route('enrollments.receiveCertificate', $enrollment));

        $this->assertSame(409, $response->status());
        $this->assertSame(EnrollmentStatus::Learning, $enrollment->fresh()->status);
    }

    public function test_receive_certificate_returns_403_for_non_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.receiveCertificate', $otherEnrollment));

        $response->assertForbidden();
    }

    public function test_receive_certificate_returns_403_for_graduated_student(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($graduated)->learning()->create();

        $response = $this->actingAs($graduated)->postJson(route('enrollments.receiveCertificate', $enrollment));

        // active-learning Middleware で 403
        $response->assertForbidden();
    }
}
