<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\UseCases\MockExamSession\GradeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_grades_session_with_mixed_correct_and_wrong_answers(): void
    {
        $mockExam = MockExam::factory()->published()->passingScore(60)->create();
        $questions = collect();
        for ($i = 0; $i < 5; $i++) {
            $questions->push(MockExamQuestion::factory()->forMockExam($mockExam)->withOptions(4, 0)->create(['order' => $i]));
        }

        $session = MockExamSession::factory()
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => $questions->pluck('id')->all(),
                'total_questions' => 5,
                'passing_score_snapshot' => 60,
            ]);

        // 3 問正解(60%) - 合格点ぴったり
        foreach ($questions as $index => $question) {
            $correctOption = $question->options->firstWhere('is_correct', true);
            $wrongOption = $question->options->firstWhere('is_correct', false);
            $selected = $index < 3 ? $correctOption : $wrongOption;

            MockExamAnswer::factory()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $selected->id,
                'selected_option_body' => $selected->body,
                'is_correct' => false,
                'answered_at' => now(),
            ]);
        }

        (app(GradeAction::class))($session);

        $session->refresh();
        $this->assertSame(MockExamSessionStatus::Graded, $session->status);
        $this->assertSame(3, $session->total_correct);
        $this->assertEquals(60.00, (float) $session->score_percentage);
        $this->assertTrue($session->pass);
    }

    public function test_grades_session_below_passing_score_as_fail(): void
    {
        $mockExam = MockExam::factory()->published()->passingScore(70)->create();
        $questions = collect();
        for ($i = 0; $i < 4; $i++) {
            $questions->push(MockExamQuestion::factory()->forMockExam($mockExam)->withOptions(4, 0)->create(['order' => $i]));
        }

        $session = MockExamSession::factory()
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => $questions->pluck('id')->all(),
                'total_questions' => 4,
                'passing_score_snapshot' => 70,
            ]);

        // 2 問正解(50%、合格点 70% 未満)
        foreach ($questions as $index => $question) {
            $correctOption = $question->options->firstWhere('is_correct', true);
            $wrongOption = $question->options->firstWhere('is_correct', false);
            $selected = $index < 2 ? $correctOption : $wrongOption;

            MockExamAnswer::factory()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $selected->id,
                'selected_option_body' => $selected->body,
                'is_correct' => false,
                'answered_at' => now(),
            ]);
        }

        (app(GradeAction::class))($session);

        $session->refresh();
        $this->assertFalse($session->pass);
        $this->assertEquals(50.00, (float) $session->score_percentage);
    }

    public function test_unanswered_questions_count_as_incorrect(): void
    {
        $mockExam = MockExam::factory()->published()->passingScore(50)->create();
        $questions = collect();
        for ($i = 0; $i < 3; $i++) {
            $questions->push(MockExamQuestion::factory()->forMockExam($mockExam)->withOptions(4, 0)->create(['order' => $i]));
        }

        $session = MockExamSession::factory()
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => $questions->pluck('id')->all(),
                'total_questions' => 3,
                'passing_score_snapshot' => 50,
            ]);

        // 1 問のみ正解、残り 2 問は未解答(MockExamAnswer レコードなし)
        $firstQuestion = $questions->first();
        $correctOption = $firstQuestion->options->firstWhere('is_correct', true);
        MockExamAnswer::factory()->create([
            'mock_exam_session_id' => $session->id,
            'mock_exam_question_id' => $firstQuestion->id,
            'selected_option_id' => $correctOption->id,
            'selected_option_body' => $correctOption->body,
            'is_correct' => false,
            'answered_at' => now(),
        ]);

        (app(GradeAction::class))($session);

        $session->refresh();
        $this->assertSame(1, $session->total_correct);
        // 1/3 = 33.33% — 50% 未満で不合格
        $this->assertFalse($session->pass);
    }
}
