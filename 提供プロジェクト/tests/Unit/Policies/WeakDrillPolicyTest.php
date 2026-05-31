<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\WeakDrillPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeakDrillPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allows_owner_with_learning_status(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $this->assertTrue(app(WeakDrillPolicy::class)->view($student, $enrollment));
    }

    public function test_view_allows_owner_with_passed_status(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Passed->value])
            ->create();

        $this->assertTrue(app(WeakDrillPolicy::class)->view($student, $enrollment));
    }

    public function test_view_denies_failed_status(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Failed->value])
            ->create();

        $this->assertFalse(app(WeakDrillPolicy::class)->view($student, $enrollment));
    }

    public function test_view_denies_other_user(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()
            ->for($other)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $this->assertFalse(app(WeakDrillPolicy::class)->view($student, $enrollment));
    }

    public function test_view_denies_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()
            ->for($admin)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $this->assertFalse(app(WeakDrillPolicy::class)->view($admin, $enrollment));
    }
}
