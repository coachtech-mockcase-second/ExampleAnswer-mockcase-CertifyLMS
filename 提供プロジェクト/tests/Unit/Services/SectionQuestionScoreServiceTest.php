<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use App\Services\SectionQuestionScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionQuestionScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_empty_for_section_without_questions(): void
    {
        $student = User::factory()->student()->create();
        $section = Section::factory()->create(['status' => ContentStatus::Published->value]);

        $summary = app(SectionQuestionScoreService::class)->summarize($student, $section);

        $this->assertSame(0, $summary->attemptCount);
        $this->assertNull($summary->bestScore);
        $this->assertNull($summary->latestScore);
        $this->assertNull($summary->accuracyRate);
    }

    public function test_summarize_returns_unattempted_when_no_answer_recorded(): void
    {
        $student = User::factory()->student()->create();
        [$section] = $this->buildScenario($student, 2);

        $summary = app(SectionQuestionScoreService::class)->summarize($student, $section);

        $this->assertSame(0, $summary->attemptCount);
        $this->assertNull($summary->bestScore);
        $this->assertNull($summary->latestScore);
        $this->assertNull($summary->accuracyRate);
    }

    public function test_summarize_aggregates_attempts_and_accuracy(): void
    {
        $student = User::factory()->student()->create();
        [$section, $q1, $q2] = $this->buildScenario($student, 2);

        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($q1)
            ->state(['attempt_count' => 2, 'correct_count' => 1, 'last_is_correct' => true, 'last_answered_at' => now()])
            ->create();
        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($q2)
            ->state(['attempt_count' => 3, 'correct_count' => 1, 'last_is_correct' => false, 'last_answered_at' => now()])
            ->create();

        $summary = app(SectionQuestionScoreService::class)->summarize($student, $section);

        $this->assertSame(5, $summary->attemptCount);
        $this->assertEqualsWithDelta(2 / 5, $summary->accuracyRate, 0.0001);
    }

    public function test_summarize_computes_best_and_latest_round_score(): void
    {
        $student = User::factory()->student()->create();
        [$section, $q1, $q2] = $this->buildScenario($student, 2);

        // Round 1: q1 正解 / q2 誤答 = 1
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($q1)
            ->state(['is_correct' => true, 'answered_at' => now()->subMinutes(20), 'source' => AnswerSource::SectionQuiz->value])
            ->create();
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($q2)
            ->state(['is_correct' => false, 'answered_at' => now()->subMinutes(19), 'source' => AnswerSource::SectionQuiz->value])
            ->create();

        // Round 2: q1 正解 / q2 正解 = 2
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($q1)
            ->state(['is_correct' => true, 'answered_at' => now()->subMinutes(10), 'source' => AnswerSource::SectionQuiz->value])
            ->create();
        SectionQuestionAnswer::factory()->forUser($student)->forQuestion($q2)
            ->state(['is_correct' => true, 'answered_at' => now()->subMinutes(9), 'source' => AnswerSource::SectionQuiz->value])
            ->create();

        $summary = app(SectionQuestionScoreService::class)->summarize($student, $section);

        $this->assertSame(2, $summary->bestScore);
        $this->assertSame(2, $summary->latestScore);
    }

    public function test_batch_summarize_keys_by_section_id(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)
            ->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $sectionA = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $sectionB = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        $result = app(SectionQuestionScoreService::class)->batchSummarize($student, $enrollment);

        $this->assertTrue($result->has($sectionA->id));
        $this->assertTrue($result->has($sectionB->id));
    }

    /**
     * @return array{0: Section, 1: SectionQuestion, 2: SectionQuestion}
     */
    private function buildScenario(User $student, int $questionCount): array
    {
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        $questions = [];
        for ($i = 0; $i < $questionCount; $i++) {
            $questions[] = SectionQuestion::factory()->forSection($section)->published()->state(['order' => $i])->create();
        }

        return [$section, ...$questions];
    }
}
