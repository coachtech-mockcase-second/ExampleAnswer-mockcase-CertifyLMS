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
use App\Policies\SectionQuizPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionQuizPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allows_learning_enrollment_with_published_cascade(): void
    {
        [$student, $section] = $this->build(EnrollmentStatus::Learning, ContentStatus::Published);

        $this->assertTrue(app(SectionQuizPolicy::class)->view($student, $section));
    }

    public function test_view_allows_passed_enrollment(): void
    {
        [$student, $section] = $this->build(EnrollmentStatus::Passed, ContentStatus::Published);

        $this->assertTrue(app(SectionQuizPolicy::class)->view($student, $section));
    }

    public function test_view_denies_failed_enrollment(): void
    {
        [$student, $section] = $this->build(EnrollmentStatus::Failed, ContentStatus::Published);

        $this->assertFalse(app(SectionQuizPolicy::class)->view($student, $section));
    }

    public function test_view_denies_draft_chapter(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Draft->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        $this->assertFalse(app(SectionQuizPolicy::class)->view($student, $section));
    }

    public function test_view_denies_admin(): void
    {
        $admin = User::factory()->admin()->create();
        [, $section] = $this->build(EnrollmentStatus::Learning, ContentStatus::Published);

        $this->assertFalse(app(SectionQuizPolicy::class)->view($admin, $section));
    }

    /**
     * @return array{0: User, 1: Section}
     */
    private function build(EnrollmentStatus $status, ContentStatus $partStatus): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $status->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => $partStatus->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        return [$student, $section];
    }
}
