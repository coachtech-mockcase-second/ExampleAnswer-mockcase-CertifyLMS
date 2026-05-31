<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 学習時間目標 CRUD の HTTP 統合テスト。
 */
class LearningHourTargetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_view_with_summary(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->get(route('learning.hourTarget.show', $enrollment))
            ->assertOk()
            ->assertViewIs('learning.hour-targets.show');
    }

    public function test_show_forbidden_for_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        $this->actingAs($student)
            ->get(route('learning.hourTarget.show', $otherEnrollment))
            ->assertForbidden();
    }

    public function test_upsert_creates_new_target(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->put(route('learning.hourTarget.upsert', $enrollment), [
                'target_total_hours' => 150,
            ])
            ->assertRedirect(route('learning.hourTarget.show', $enrollment));

        $this->assertDatabaseHas('learning_hour_targets', [
            'enrollment_id' => $enrollment->id,
            'target_total_hours' => 150,
        ]);
    }

    public function test_upsert_validation_fails_for_zero_hours(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->put(route('learning.hourTarget.upsert', $enrollment), [
                'target_total_hours' => 0,
            ])
            ->assertSessionHasErrors('target_total_hours');
    }

    public function test_destroy_soft_deletes_target(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $target = LearningHourTarget::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->delete(route('learning.hourTarget.destroy', $enrollment))
            ->assertRedirect();

        $this->assertDatabaseMissing('learning_hour_targets', ['id' => $target->id]);
    }
}
