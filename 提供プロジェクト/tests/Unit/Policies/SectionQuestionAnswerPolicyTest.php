<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\User;
use App\Policies\SectionQuestionAnswerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionQuestionAnswerPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allows_owner_only(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $answer = SectionQuestionAnswer::factory()->forUser($student)->create();

        $policy = app(SectionQuestionAnswerPolicy::class);
        $this->assertTrue($policy->view($student, $answer));
        $this->assertFalse($policy->view($other, $answer));
    }

    public function test_create_allows_learning_enrollment_with_full_cascade_publish(): void
    {
        [$student, $question] = $this->buildScenario(EnrollmentStatus::Learning, ContentStatus::Published);

        $this->assertTrue(app(SectionQuestionAnswerPolicy::class)->create($student, $question));
    }

    public function test_create_allows_passed_enrollment(): void
    {
        [$student, $question] = $this->buildScenario(EnrollmentStatus::Passed, ContentStatus::Published);

        $this->assertTrue(app(SectionQuestionAnswerPolicy::class)->create($student, $question));
    }

    public function test_create_denies_failed_enrollment(): void
    {
        [$student, $question] = $this->buildScenario(EnrollmentStatus::Failed, ContentStatus::Published);

        $this->assertFalse(app(SectionQuestionAnswerPolicy::class)->create($student, $question));
    }

    public function test_create_denies_when_question_is_draft(): void
    {
        [$student, $question] = $this->buildScenario(EnrollmentStatus::Learning, ContentStatus::Draft);

        $this->assertFalse(app(SectionQuestionAnswerPolicy::class)->create($student, $question));
    }

    public function test_create_denies_non_student(): void
    {
        $admin = User::factory()->admin()->create();
        [, $question] = $this->buildScenario(EnrollmentStatus::Learning, ContentStatus::Published);

        $this->assertFalse(app(SectionQuestionAnswerPolicy::class)->create($admin, $question));
    }

    public function test_create_denies_graduated_user_status(): void
    {
        [$student, $question] = $this->buildScenario(EnrollmentStatus::Learning, ContentStatus::Published);
        $student->update(['status' => UserStatus::Graduated->value]);

        $this->assertFalse(app(SectionQuestionAnswerPolicy::class)->create($student->fresh(), $question));
    }

    /**
     * @return array{0: User, 1: SectionQuestion}
     */
    private function buildScenario(EnrollmentStatus $enrollmentStatus, ContentStatus $questionStatus): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $enrollmentStatus->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->state([
            'status' => $questionStatus->value,
        ])->create();

        return [$student, $question];
    }
}
