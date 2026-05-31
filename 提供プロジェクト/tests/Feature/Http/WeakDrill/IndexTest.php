<?php

declare(strict_types=1);

namespace Tests\Feature\Http\WeakDrill;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_drill_index_even_without_weakness_binding(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        QuestionCategory::factory()->for($certification)->count(2)->create();

        $this->actingAs($student)
            ->get(route('quiz.drills.index', $enrollment))
            ->assertOk();
    }

    public function test_other_student_returns_403(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($other)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $this->actingAs($student)
            ->get(route('quiz.drills.index', $enrollment))
            ->assertForbidden();
    }

    public function test_failed_enrollment_returns_403(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Failed->value])
            ->create();

        $this->actingAs($student)
            ->get(route('quiz.drills.index', $enrollment))
            ->assertForbidden();
    }
}
