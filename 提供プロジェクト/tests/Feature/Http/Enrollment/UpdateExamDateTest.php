<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講生本人による目標受験日設定 (`enrollments.updateExamDate`) の検証。
 * 本人は設定 / 変更でき、他受講生は 403、passed は 403、過去日は 422。
 */
class UpdateExamDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_set_own_exam_date(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create(['exam_date' => null]);
        $examDate = now()->addDays(30)->toDateString();

        // Act
        $response = $this->actingAs($student)
            ->from(route('enrollments.show', $enrollment))
            ->patch(route('enrollments.updateExamDate', $enrollment), ['exam_date' => $examDate]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'exam_date' => $examDate,
        ]);
    }

    public function test_other_student_cannot_update_exam_date(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();

        // Act
        $response = $this->actingAs($other)
            ->patchJson(route('enrollments.updateExamDate', $enrollment), ['exam_date' => now()->addDays(10)->toDateString()]);

        // Assert
        $response->assertForbidden();
    }

    public function test_cannot_update_exam_date_for_passed_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create(['passed_at' => now()]);

        // Act
        $response = $this->actingAs($student)
            ->patchJson(route('enrollments.updateExamDate', $enrollment), ['exam_date' => now()->addDays(10)->toDateString()]);

        // Assert
        $response->assertForbidden();
    }

    public function test_exam_date_must_be_after_today(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)
            ->patchJson(route('enrollments.updateExamDate', $enrollment), ['exam_date' => now()->subDay()->toDateString()]);

        // Assert
        $response->assertStatus(422);
    }
}
