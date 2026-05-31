<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\User;
use App\Policies\LearningHourTargetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningHourTargetPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allowed_for_owning_student(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $this->assertTrue(app(LearningHourTargetPolicy::class)->create($student, $enrollment));
    }

    public function test_create_denied_for_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assertFalse(app(LearningHourTargetPolicy::class)->create($coach, $enrollment));
    }

    public function test_delete_denied_for_other_student(): void
    {
        $student = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $this->assertFalse(app(LearningHourTargetPolicy::class)->delete($student, $otherEnrollment));
    }
}
