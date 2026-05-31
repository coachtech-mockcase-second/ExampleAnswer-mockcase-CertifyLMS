<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use App\Services\SectionQuestionAttemptStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionQuestionAttemptStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_null_accuracy_for_empty(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();

        $summary = app(SectionQuestionAttemptStatsService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->totalQuestionsAttempted);
        $this->assertSame(0, $summary->totalAttempts);
        $this->assertSame(0, $summary->totalCorrect);
        $this->assertNull($summary->overallAccuracy);
        $this->assertNull($summary->lastAnsweredAt);
    }

    public function test_summarize_isolates_enrollment_certification(): void
    {
        $student = User::factory()->student()->create();
        [$enrollmentA, $questionA] = $this->buildEnrollmentWithQuestion($student);
        [, $questionB] = $this->buildEnrollmentWithQuestion($student);

        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($questionA)
            ->state(['attempt_count' => 3, 'correct_count' => 2, 'last_is_correct' => true, 'last_answered_at' => now()])
            ->create();
        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($questionB)
            ->state(['attempt_count' => 5, 'correct_count' => 1, 'last_is_correct' => false, 'last_answered_at' => now()])
            ->create();

        $summary = app(SectionQuestionAttemptStatsService::class)->summarize($enrollmentA);

        $this->assertSame(1, $summary->totalQuestionsAttempted);
        $this->assertSame(3, $summary->totalAttempts);
        $this->assertSame(2, $summary->totalCorrect);
        $this->assertEqualsWithDelta(2 / 3, $summary->overallAccuracy, 0.0001);
    }

    public function test_by_category_groups_attempts(): void
    {
        $student = User::factory()->student()->create();
        [$enrollment, $question1, $question2, $categoryA, $categoryB] = $this->buildTwoCategoryScenario($student);

        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($question1)
            ->state(['attempt_count' => 4, 'correct_count' => 3])->create();
        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($question2)
            ->state(['attempt_count' => 2, 'correct_count' => 0])->create();

        $stats = app(SectionQuestionAttemptStatsService::class)->byCategory($enrollment);
        $byCategory = $stats->keyBy(fn ($s) => $s->categoryId);

        $this->assertEqualsWithDelta(0.75, $byCategory[$categoryA->id]->accuracy, 0.0001);
        $this->assertEqualsWithDelta(0.0, $byCategory[$categoryB->id]->accuracy, 0.0001);
    }

    public function test_recent_answers_limits_count(): void
    {
        $student = User::factory()->student()->create();
        [$enrollment, $question] = $this->buildEnrollmentWithQuestion($student);

        for ($i = 0; $i < 10; $i++) {
            SectionQuestionAnswer::factory()
                ->forUser($student)
                ->forQuestion($question)
                ->state(['answered_at' => now()->subMinutes(10 - $i)])
                ->create();
        }

        $recent = app(SectionQuestionAttemptStatsService::class)->recentAnswers($enrollment, 5);

        $this->assertCount(5, $recent);
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

    /**
     * @return array{0: Enrollment, 1: SectionQuestion, 2: SectionQuestion, 3: QuestionCategory, 4: QuestionCategory}
     */
    private function buildTwoCategoryScenario(User $student): array
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        $categoryA = QuestionCategory::factory()->for($certification)->create();
        $categoryB = QuestionCategory::factory()->for($certification)->create();

        $question1 = SectionQuestion::factory()->forSection($section)->forCategory($categoryA)->published()->create();
        $question2 = SectionQuestion::factory()->forSection($section)->forCategory($categoryB)->published()->create();

        return [$enrollment, $question1, $question2, $categoryA, $categoryB];
    }
}
