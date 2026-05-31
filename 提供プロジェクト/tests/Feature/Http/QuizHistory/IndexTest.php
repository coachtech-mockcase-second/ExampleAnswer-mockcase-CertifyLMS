<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QuizHistory;

use App\Enums\AnswerSource;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_history_filtered_by_certification(): void
    {
        $student = User::factory()->student()->create();
        [$enrollment, $sectionQuestion] = $this->buildEnrollmentWithQuestion($student);
        [, $otherQuestion] = $this->buildEnrollmentWithQuestion($student);

        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($sectionQuestion)->correct()->create();
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($otherQuestion)->incorrect()->create();

        $response = $this->actingAs($student)->get(route('quiz.history.index', $enrollment));
        $response->assertOk();
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
            ->get(route('quiz.history.index', $enrollment))
            ->assertForbidden();
    }

    public function test_filter_by_source(): void
    {
        $student = User::factory()->student()->create();
        [$enrollment, $question] = $this->buildEnrollmentWithQuestion($student);

        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($question)
            ->source(AnswerSource::SectionQuiz)->create();
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($question)
            ->source(AnswerSource::WeakDrill)->create();

        $response = $this->actingAs($student)
            ->get(route('quiz.history.index', $enrollment).'?source=section_quiz');
        $response->assertOk();
    }

    /**
     * @return array{0: Enrollment, 1: SectionQuestion}
     */
    private function buildEnrollmentWithQuestion(User $student): array
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->published()->create();

        return [$enrollment, $question];
    }
}
