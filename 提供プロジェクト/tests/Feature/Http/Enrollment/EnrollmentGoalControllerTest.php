<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_student_can_create_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解く',
            'target_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問 5 年分を解く',
            'achieved_at' => null,
        ]);
    }

    public function test_other_student_cannot_create_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($student)->postJson(route('enrollments.goals.store', $otherEnrollment), [
            'title' => 'evil',
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_create_goal_even_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_at' => now(),
            'assigned_by_user_id' => $coach->id,
        ]);

        $response = $this->actingAs($coach)->postJson(route('enrollments.goals.store', $enrollment), [
            'title' => 'evil',
        ]);

        // coach は閲覧専用、CRUD 不可
        $response->assertForbidden();
    }

    public function test_owner_student_can_mark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create(['achieved_at' => null]);

        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        $response->assertRedirect();
        $this->assertNotNull($goal->fresh()->achieved_at);
    }

    public function test_owner_student_can_unmark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->achieved()->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        $response->assertRedirect();
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_owner_student_can_delete_own_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.destroy', $goal));

        $response->assertRedirect();
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_owner_student_can_update_own_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create([
            'title' => '旧タイトル',
            'description' => '旧詳細',
        ]);

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '新タイトル',
            'description' => '新詳細',
            'target_date' => now()->addMonths(2)->toDateString(),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '新タイトル',
            'description' => '新詳細',
        ]);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();
        $goal = EnrollmentGoal::factory()->for($otherEnrollment)->create();

        $response = $this->actingAs($student)->patchJson(route('enrollment-goals.update', $goal), [
            'title' => 'evil',
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_update_goal(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_at' => now(),
            'assigned_by_user_id' => $coach->id,
        ]);
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($coach)->patchJson(route('enrollment-goals.update', $goal), [
            'title' => 'evil',
        ]);

        // coach は閲覧専用、CRUD 不可
        $response->assertForbidden();
    }

    public function test_owner_student_can_get_edit_view(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->get(route('enrollment-goals.edit', $goal));

        $response->assertOk();
        $response->assertViewIs('enrollment-goal.edit');
    }
}
