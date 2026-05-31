<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionQuiz;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_student_can_view_section_entry(): void
    {
        [$student, $section] = $this->buildScenario(EnrollmentStatus::Learning);
        SectionQuestion::factory()->forSection($section)->published()->withOptions(2)->create();

        $this->actingAs($student)
            ->get(route('quiz.sections.show', $section))
            ->assertOk();
    }

    public function test_passed_student_can_view_section_entry(): void
    {
        [$student, $section] = $this->buildScenario(EnrollmentStatus::Passed);
        SectionQuestion::factory()->forSection($section)->published()->withOptions(2)->create();

        $this->actingAs($student)
            ->get(route('quiz.sections.show', $section))
            ->assertOk();
    }

    public function test_failed_enrollment_returns_404(): void
    {
        [$student, $section] = $this->buildScenario(EnrollmentStatus::Failed);

        $this->actingAs($student)
            ->get(route('quiz.sections.show', $section))
            ->assertNotFound();
    }

    public function test_admin_blocked_by_role_middleware(): void
    {
        $admin = User::factory()->admin()->create();
        [, $section] = $this->buildScenario(EnrollmentStatus::Learning);

        $this->actingAs($admin)
            ->get(route('quiz.sections.show', $section))
            ->assertForbidden();
    }

    public function test_graduated_blocked_by_middleware(): void
    {
        [$student, $section] = $this->buildScenario(EnrollmentStatus::Learning);
        $student->update(['status' => UserStatus::Graduated->value]);

        $this->actingAs($student->fresh())
            ->get(route('quiz.sections.show', $section))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Section}
     */
    private function buildScenario(EnrollmentStatus $status): array
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
