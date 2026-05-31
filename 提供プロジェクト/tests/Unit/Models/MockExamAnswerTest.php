<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamQuestionOption;
use App\Models\MockExamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MockExamAnswer モデルのリレーション・Cast を検証する Unit テスト。
 * 3 リレーション (mockExamSession / mockExamQuestion / selectedOption) + 2 cast (is_correct bool / answered_at datetime) を網羅する。
 */
class MockExamAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_exam_session_relation_returns_parent_session(): void
    {
        // Arrange
        $session = MockExamSession::factory()->inProgress()->create();
        $answer = MockExamAnswer::factory()->for($session, 'mockExamSession')->create();

        // Act
        $parent = $answer->mockExamSession;

        // Assert
        $this->assertTrue($parent->is($session));
    }

    public function test_mock_exam_question_relation_returns_target_question(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create();
        $answer = MockExamAnswer::factory()->for($question, 'mockExamQuestion')->create();

        // Act
        $target = $answer->mockExamQuestion;

        // Assert
        $this->assertTrue($target->is($question));
    }

    public function test_selected_option_relation_returns_chosen_option(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create();
        $option = MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->correct()->create();
        $answer = MockExamAnswer::factory()
            ->for($question, 'mockExamQuestion')
            ->for($option, 'selectedOption')
            ->create();

        // Act
        $chosen = $answer->selectedOption;

        // Assert
        $this->assertNotNull($chosen);
        $this->assertTrue($chosen->is($option));
    }

    public function test_is_correct_cast_returns_boolean(): void
    {
        // Arrange
        $answer = MockExamAnswer::factory()->correct()->create();

        // Act
        $fresh = $answer->fresh();

        // Assert
        $this->assertIsBool($fresh->is_correct);
        $this->assertTrue($fresh->is_correct);
    }

    public function test_answered_at_cast_returns_carbon(): void
    {
        // Arrange
        $answer = MockExamAnswer::factory()->create([
            'answered_at' => '2026-05-20 15:00:00',
        ]);

        // Act
        $fresh = $answer->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->answered_at);
    }
}
