<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者向け EnrollmentManagementController の HTTP 統合テスト。
 * updateExamDate / fail の admin 専用業務操作を検証する(一覧 / 詳細は EnrollmentControllerTest に統合済)。
 */
class EnrollmentManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_exam_date_succeeds_for_learning_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create(['exam_date' => null]);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.enrollments.updateExamDate', $enrollment), [
            'exam_date' => now()->addMonths(2)->toDateString(),
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertNotNull($enrollment->fresh()->exam_date);
    }

    public function test_admin_update_exam_date_forbidden_for_passed_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.enrollments.updateExamDate', $enrollment), [
            'exam_date' => now()->addMonth()->toDateString(),
        ]);

        // Assert
        // Policy::updateExamDate が status=Passed を弾く → 403
        $response->assertForbidden();
    }

    public function test_admin_can_fail_learning_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.enrollments.fail', $enrollment), [
            'reason' => '本人辞退',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
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
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->passed()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.enrollments.fail', $enrollment));

        // Assert
        // Policy::fail が status=Learning に絞っているため 403
        $response->assertForbidden();
    }
}
