<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Policies\SectionViewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionViewPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allowed_for_learning_enrollment(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Learning);

        $this->assertTrue(app(SectionViewPolicy::class)->view($student, $section));
    }

    public function test_view_allowed_for_passed_enrollment(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Passed);

        $this->assertTrue(app(SectionViewPolicy::class)->view($student, $section));
    }

    public function test_view_denied_for_failed_enrollment(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Failed);

        $this->assertFalse(app(SectionViewPolicy::class)->view($student, $section));
    }

    public function test_view_denied_for_non_student(): void
    {
        $admin = User::factory()->admin()->create();
        $section = Section::factory()->create();

        $this->assertFalse(app(SectionViewPolicy::class)->view($admin, $section));
    }

    /**
     * @return array{0: User, 1: Section}
     */
    private function buildSection(EnrollmentStatus $status): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $status->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        return [$student, $section];
    }
}
