<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDefaultEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_set_default_to_own_learning_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success');
        $this->assertSame($enrollment->id, $student->fresh()->default_enrollment_id);
    }

    public function test_student_can_set_default_to_own_passed_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertRedirect();
        $this->assertSame($enrollment->id, $student->fresh()->default_enrollment_id);
    }

    public function test_redirect_to_parameter_overrides_default_redirect(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $enrollment), [
                'redirect_to' => '/enrollments',
            ]);

        $response->assertRedirect('/enrollments');
    }

    public function test_student_cannot_set_default_to_other_users_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $otherEnrollment));

        $response->assertForbidden();
        $this->assertNull($student->fresh()->default_enrollment_id);
    }

    public function test_failed_enrollment_returns_422(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->failed()->create();

        $response = $this->actingAs($student)
            ->putJson(route('settings.default-enrollment.update', $enrollment));

        $response->assertStatus(422);
        $this->assertNull($student->fresh()->default_enrollment_id);
    }

    public function test_soft_deleted_enrollment_returns_404(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $enrollment->delete();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $enrollment->id));

        $response->assertNotFound();
    }

    public function test_coach_is_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($coach)
            ->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($admin)
            ->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertForbidden();
    }

    public function test_graduated_student_is_forbidden_by_active_learning_middleware(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $response = $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $response = $this->put(route('settings.default-enrollment.update', $enrollment));

        $response->assertRedirect('/login');
    }

    public function test_overwrites_existing_default(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $first = Enrollment::factory()->for($student)->learning()->create();
        $student->update(['default_enrollment_id' => $first->id]);
        $second = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->put(route('settings.default-enrollment.update', $second));

        $this->assertSame($second->id, $student->fresh()->default_enrollment_id);
    }
}
