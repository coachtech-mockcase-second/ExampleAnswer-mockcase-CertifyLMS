<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者向け AdminEnrollmentController の HTTP 統合テスト。
 * 認可漏れ / 状態遷移整合性 / 代表的な正常系を網羅する。
 */
class AdminEnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_enrollments(): void
    {
        $admin = User::factory()->admin()->create();
        Enrollment::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.enrollments.index'));

        $response->assertOk();
        $response->assertViewIs('admin.enrollments.index');
    }

    public function test_coach_is_forbidden_from_admin_enrollments_index(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('admin.enrollments.index'));

        $response->assertForbidden();
    }

    public function test_student_is_forbidden_from_admin_enrollments_index(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('admin.enrollments.index'));

        $response->assertForbidden();
    }

    public function test_admin_update_exam_date_succeeds_for_learning_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create(['exam_date' => null]);

        $response = $this->actingAs($admin)->patch(route('admin.enrollments.updateExamDate', $enrollment), [
            'exam_date' => now()->addMonths(2)->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertNotNull($enrollment->fresh()->exam_date);
    }

    public function test_admin_update_exam_date_forbidden_for_passed_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        $response = $this->actingAs($admin)->patchJson(route('admin.enrollments.updateExamDate', $enrollment), [
            'exam_date' => now()->addMonth()->toDateString(),
        ]);

        // Policy::updateExamDate が status=Passed を弾く → 403
        $response->assertForbidden();
    }

    public function test_admin_can_fail_learning_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($admin)->post(route('admin.enrollments.fail', $enrollment), [
            'reason' => '本人辞退',
        ]);

        $response->assertRedirect();
        $this->assertSame(EnrollmentStatus::Failed, $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Failed->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => '本人辞退',
        ]);
    }

    public function test_admin_fail_forbidden_for_passed_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.enrollments.fail', $enrollment));

        // Policy::fail が status=Learning に絞っているため 403
        $response->assertForbidden();
    }

    public function test_admin_show_includes_soft_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $enrollment->delete();

        $response = $this->actingAs($admin)->get(route('admin.enrollments.show', $enrollment->id));

        $response->assertOk();
    }
}
