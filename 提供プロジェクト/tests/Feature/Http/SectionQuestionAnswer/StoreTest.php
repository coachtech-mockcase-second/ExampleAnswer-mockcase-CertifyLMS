<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionQuestionAnswer;

use App\Enums\AnswerSource;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_quiz_correct_answer_redirects_to_result(): void
    {
        [$student, $section, $question] = $this->buildSectionScenario();
        $correctOption = $question->options->firstWhere('is_correct', true);

        $response = $this->actingAs($student)
            ->from(route('quiz.sections.question', ['section' => $section, 'question' => $question]))
            ->post(route('quiz.answers.store', $question), [
                'selected_option_id' => $correctOption->id,
                'source' => AnswerSource::SectionQuiz->value,
                'section_id' => $section->id,
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/quiz/sections/'.$section->id.'/questions/'.$question->id.'/result/', $response->headers->get('Location'));
        $this->assertDatabaseCount('section_question_answers', 1);
        $this->assertDatabaseHas('section_question_attempts', [
            'user_id' => $student->id,
            'section_question_id' => $question->id,
            'attempt_count' => 1,
            'correct_count' => 1,
            'last_is_correct' => true,
        ]);
    }

    public function test_weak_drill_answer_redirects_to_drill_result(): void
    {
        [$student, $enrollment, $category, $question] = $this->buildDrillScenario();
        $option = $question->options->first();

        $response = $this->actingAs($student)
            ->from(route('quiz.drills.question', ['enrollment' => $enrollment, 'questionCategory' => $category, 'question' => $question]))
            ->post(route('quiz.answers.store', $question), [
                'selected_option_id' => $option->id,
                'source' => AnswerSource::WeakDrill->value,
                'enrollment_id' => $enrollment->id,
                'question_category_id' => $category->id,
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/quiz/drills/'.$enrollment->id, $response->headers->get('Location'));
        $this->assertStringContainsString('/result/', $response->headers->get('Location'));
    }

    public function test_invalid_option_returns_validation_error(): void
    {
        [$student, $section, $question] = $this->buildSectionScenario();
        $otherQuestion = SectionQuestion::factory()->published()->withOptions(2)->create();
        $foreignOption = $otherQuestion->options->first();

        $response = $this->actingAs($student)
            ->from(route('quiz.sections.question', ['section' => $section, 'question' => $question]))
            ->post(route('quiz.answers.store', $question), [
                'selected_option_id' => $foreignOption->id,
                'source' => AnswerSource::SectionQuiz->value,
                'section_id' => $section->id,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('section_question_answers', 0);
    }

    public function test_failed_enrollment_denied(): void
    {
        [$student, $section, $question] = $this->buildSectionScenario(EnrollmentStatus::Failed);
        $option = $question->options->first();

        $response = $this->actingAs($student)
            ->from(route('quiz.sections.question', ['section' => $section, 'question' => $question]))
            ->post(route('quiz.answers.store', $question), [
                'selected_option_id' => $option->id,
                'source' => AnswerSource::SectionQuiz->value,
                'section_id' => $section->id,
            ]);

        $response->assertForbidden();
    }

    public function test_graduated_student_blocked_by_middleware(): void
    {
        [$student, $section, $question] = $this->buildSectionScenario();
        $student->update(['status' => UserStatus::Graduated->value]);
        $option = $question->options->first();

        $response = $this->actingAs($student->fresh())
            ->post(route('quiz.answers.store', $question), [
                'selected_option_id' => $option->id,
                'source' => AnswerSource::SectionQuiz->value,
                'section_id' => $section->id,
            ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Section, 2: SectionQuestion}
     */
    private function buildSectionScenario(EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Learning): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $enrollmentStatus->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->published()->withOptions(3)->create();

        return [$student, $section, $question];
    }

    /**
     * @return array{0: User, 1: Enrollment, 2: QuestionCategory, 3: SectionQuestion}
     */
    private function buildDrillScenario(): array
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $category = QuestionCategory::factory()->for($certification)->create();
        $question = SectionQuestion::factory()->forSection($section)->forCategory($category)->published()->withOptions(3)->create();

        return [$student, $enrollment, $category, $question];
    }
}
