<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\SectionQuestionAnswer;

use App\Enums\AnswerSource;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\QuizAnswering\EnrollmentInactiveForAnswerException;
use App\Exceptions\QuizAnswering\SectionQuestionOptionMismatchException;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use App\Models\User;
use App\UseCases\SectionQuestionAnswer\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_answer_inserts_answer_and_upserts_attempt(): void
    {
        [$student, $question, $correctOption] = $this->buildAnswerableScenario();

        $result = app(StoreAction::class)($student, $question, $correctOption, AnswerSource::SectionQuiz);

        $this->assertTrue($result->answer->is_correct);
        $this->assertSame(1, $result->attempt->attempt_count);
        $this->assertSame(1, $result->attempt->correct_count);
        $this->assertTrue($result->attempt->last_is_correct);
        $this->assertDatabaseHas('section_question_answers', [
            'id' => $result->answer->id,
            'is_correct' => true,
            'source' => AnswerSource::SectionQuiz->value,
        ]);
    }

    public function test_repeated_answer_increments_attempt_count(): void
    {
        [$student, $question, $correctOption, $wrongOption] = $this->buildAnswerableScenario(returnWrong: true);

        app(StoreAction::class)($student, $question, $correctOption, AnswerSource::SectionQuiz);
        app(StoreAction::class)($student, $question, $wrongOption, AnswerSource::SectionQuiz);
        $third = app(StoreAction::class)($student, $question, $correctOption, AnswerSource::WeakDrill);

        $this->assertSame(3, $third->attempt->attempt_count);
        $this->assertSame(2, $third->attempt->correct_count);
        $this->assertTrue($third->attempt->last_is_correct);
        $this->assertDatabaseCount('section_question_answers', 3);
        $this->assertDatabaseCount('section_question_attempts', 1);
    }

    public function test_option_mismatch_throws(): void
    {
        [$student, $question] = $this->buildAnswerableScenario();
        $otherQuestion = SectionQuestion::factory()->published()->withOptions(2)->create();
        $foreignOption = $otherQuestion->options->first();

        $this->expectException(SectionQuestionOptionMismatchException::class);

        app(StoreAction::class)($student, $question, $foreignOption, AnswerSource::SectionQuiz);
    }

    public function test_draft_question_throws(): void
    {
        [$student, $question, $correctOption] = $this->buildAnswerableScenario();
        $question->update(['status' => ContentStatus::Draft->value]);

        $this->expectException(SectionQuestionUnavailableForAnswerException::class);

        app(StoreAction::class)($student, $question->fresh(), $correctOption, AnswerSource::SectionQuiz);
    }

    public function test_failed_enrollment_throws(): void
    {
        [$student, $question, $correctOption] = $this->buildAnswerableScenario(enrollmentStatus: EnrollmentStatus::Failed);

        $this->expectException(EnrollmentInactiveForAnswerException::class);

        app(StoreAction::class)($student, $question, $correctOption, AnswerSource::SectionQuiz);
    }

    /**
     * @return array{0: User, 1: SectionQuestion, 2: SectionQuestionOption, 3?: SectionQuestionOption}
     */
    private function buildAnswerableScenario(
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Learning,
        bool $returnWrong = false,
    ): array {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $enrollmentStatus->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->published()->withOptions(2)->create();

        $correct = $question->options->firstWhere('is_correct', true);
        $wrong = $question->options->firstWhere('is_correct', false);

        if ($returnWrong) {
            return [$student, $question, $correct, $wrong];
        }

        return [$student, $question, $correct];
    }
}
