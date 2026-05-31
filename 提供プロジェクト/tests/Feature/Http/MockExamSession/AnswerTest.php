<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExamSession;

use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_saves_answer_upsert(): void
    {
        [$student, $session, $question] = $this->makeSessionWithQuestion();
        $correctOption = $question->options->firstWhere('is_correct', true);

        $this->actingAs($student)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $correctOption->id,
            ])
            ->assertOk()
            ->assertJsonFragment(['mock_exam_question_id' => $question->id]);

        $this->assertDatabaseHas('mock_exam_answers', [
            'mock_exam_session_id' => $session->id,
            'mock_exam_question_id' => $question->id,
            'selected_option_id' => $correctOption->id,
        ]);
    }

    public function test_patch_overwrites_previous_answer_on_same_question(): void
    {
        [$student, $session, $question] = $this->makeSessionWithQuestion();
        $optionA = $question->options[0];
        $optionB = $question->options[1];

        MockExamAnswer::factory()->create([
            'mock_exam_session_id' => $session->id,
            'mock_exam_question_id' => $question->id,
            'selected_option_id' => $optionA->id,
            'selected_option_body' => $optionA->body,
            'is_correct' => false,
            'answered_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $optionB->id,
            ])
            ->assertOk();

        // 同 session × 同 question は UNIQUE 制約により 1 レコードに集約
        $this->assertSame(1, MockExamAnswer::query()
            ->where('mock_exam_session_id', $session->id)
            ->where('mock_exam_question_id', $question->id)
            ->count());

        $this->assertDatabaseHas('mock_exam_answers', [
            'mock_exam_session_id' => $session->id,
            'mock_exam_question_id' => $question->id,
            'selected_option_id' => $optionB->id,
        ]);
    }

    public function test_patch_rejects_when_session_not_in_progress(): void
    {
        $student = User::factory()->student()->create();
        $mockExam = MockExam::factory()->published()->create();
        $question = MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();
        $session = MockExamSession::factory()
            ->forUser($student)
            ->forMockExam($mockExam)
            ->notStarted()
            ->create([
                'generated_question_ids' => [$question->id],
                'total_questions' => 1,
            ]);

        $this->actingAs($student)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $question->options->first()->id,
            ])
            ->assertStatus(409);
    }

    public function test_patch_rejects_question_not_in_generated_ids(): void
    {
        [$student, $session, $question] = $this->makeSessionWithQuestion();

        $foreignMockExam = MockExam::factory()->published()->create();
        $foreignQuestion = MockExamQuestion::factory()->forMockExam($foreignMockExam)->withOptions()->create();

        $this->actingAs($student)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $foreignQuestion->id,
                'selected_option_id' => $foreignQuestion->options->first()->id,
            ])
            ->assertStatus(422);
    }

    public function test_patch_rejects_option_not_belonging_to_question(): void
    {
        [$student, $session, $question] = $this->makeSessionWithQuestion();

        $otherQuestion = MockExamQuestion::factory()->forMockExam($question->mockExam)->withOptions()->create();
        $foreignOption = $otherQuestion->options->first();

        $this->actingAs($student)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $foreignOption->id,
            ])
            ->assertStatus(422);
    }

    public function test_patch_blocked_for_other_users_session(): void
    {
        [, $session, $question] = $this->makeSessionWithQuestion();
        $other = User::factory()->student()->create();

        $this->actingAs($other)
            ->patchJson(route('mock-exam-sessions.answers.update', $session), [
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $question->options->first()->id,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: MockExamSession, 2: MockExamQuestion}
     */
    private function makeSessionWithQuestion(): array
    {
        $student = User::factory()->student()->create();
        $mockExam = MockExam::factory()->published()->create();
        $question = MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();
        $session = MockExamSession::factory()
            ->forUser($student)
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => [$question->id],
                'total_questions' => 1,
            ]);

        return [$student, $session, $question];
    }
}
