<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\SectionProgress;
use App\Models\User;
use App\Policies\SectionProgressPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionProgressPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allowed_for_own_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $progress = SectionProgress::factory()->forEnrollment($enrollment)->create();

        $this->assertTrue(app(SectionProgressPolicy::class)->view($student, $progress));
    }

    public function test_view_denied_for_other_student(): void
    {
        $student = User::factory()->student()->create();
        $progress = SectionProgress::factory()->create();

        $this->assertFalse(app(SectionProgressPolicy::class)->view($student, $progress));
    }

    public function test_create_denied_for_coach(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertFalse(app(SectionProgressPolicy::class)->create($coach));
    }

    public function test_create_allowed_for_student(): void
    {
        $student = User::factory()->student()->create();

        $this->assertTrue(app(SectionProgressPolicy::class)->create($student));
    }
}
