<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MockExamQuestion;
use App\Models\MockExamQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MockExamQuestionOption モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 1 リレーション (mockExamQuestion) + 1 scope (ordered) + 2 cast (is_correct bool / order int) を網羅する。
 */
class MockExamQuestionOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_exam_question_relation_returns_parent_question(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create();
        $option = MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->create();

        // Act
        $parent = $option->mockExamQuestion;

        // Assert
        $this->assertTrue($parent->is($question));
    }

    public function test_scope_ordered_sorts_by_order(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create();
        $second = MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->create(['order' => 2]);
        $first = MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->create(['order' => 1]);

        // Act
        $results = MockExamQuestionOption::ordered()->where('mock_exam_question_id', $question->id)->get();

        // Assert
        $this->assertTrue($results->first()->is($first));
    }

    public function test_is_correct_cast_returns_boolean(): void
    {
        // Arrange
        $option = MockExamQuestionOption::factory()->correct()->create();

        // Act
        $fresh = $option->fresh();

        // Assert
        $this->assertIsBool($fresh->is_correct, 'is_correct は boolean にキャストされるはず');
        $this->assertTrue($fresh->is_correct);
    }

    public function test_order_cast_returns_integer(): void
    {
        // Arrange
        $option = MockExamQuestionOption::factory()->create(['order' => '2']);

        // Act
        $fresh = $option->fresh();

        // Assert
        $this->assertIsInt($fresh->order);
        $this->assertSame(2, $fresh->order);
    }
}
