<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionQuiz;

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

class ResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_result(): void
    {
        [$student, $section, $question, $answer] = $this->buildScenario();

        $this->actingAs($student)
            ->get(route('quiz.sections.result', ['section' => $section, 'question' => $question, 'answer' => $answer]))
            ->assertOk()
            ->assertSee($answer->selected_option_body);
    }

    public function test_other_student_cannot_view_result(): void
    {
        [, $section, $question, $answer] = $this->buildScenario();
        $other = User::factory()->student()->create();
        Enrollment::factory()->for($other)->for($section->chapter->part->certification)->state(['status' => EnrollmentStatus::Learning->value])->create();

        $this->actingAs($other)
            ->get(route('quiz.sections.result', ['section' => $section, 'question' => $question, 'answer' => $answer]))
            ->assertForbidden();
    }

    public function test_mismatched_question_section_returns_404(): void
    {
        [$student, , $question, $answer] = $this->buildScenario();
        $otherSection = Section::factory()->create(['status' => ContentStatus::Published->value]);

        $this->actingAs($student)
            ->get(route('quiz.sections.result', ['section' => $otherSection, 'question' => $question, 'answer' => $answer]))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Section, 2: SectionQuestion, 3: SectionQuestionAnswer}
     */
    private function buildScenario(): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->published()->withOptions(2)->create();
        $option = $question->options->first();

        $answer = SectionQuestionAnswer::factory()
            ->forUser($student)
            ->forQuestion($question)
            ->forOption($option)
            ->source(AnswerSource::SectionQuiz)
            ->create();

        return [$student, $section, $question, $answer];
    }
}
