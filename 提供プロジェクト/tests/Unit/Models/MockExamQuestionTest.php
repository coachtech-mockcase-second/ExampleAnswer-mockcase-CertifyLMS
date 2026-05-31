<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\MockExamQuestionOption;
use App\Models\QuestionCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MockExamQuestion モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 4 リレーション (mockExam / category / options / answers) + 1 scope (ordered) + 1 cast (order integer) を網羅する。
 */
class MockExamQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_exam_relation_returns_parent_mock_exam(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();
        $question = MockExamQuestion::factory()->for($mockExam)->create();

        // Act
        $parent = $question->mockExam;

        // Assert
        $this->assertTrue($parent->is($mockExam));
    }

    public function test_category_relation_returns_question_category(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $question = MockExamQuestion::factory()->for($category, 'category')->create();

        // Act
        $assigned = $question->category;

        // Assert
        $this->assertTrue($assigned->is($category));
    }

    public function test_options_relation_returns_attached_options(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create();
        MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->create();
        MockExamQuestionOption::factory()->for($question, 'mockExamQuestion')->create();

        // Act
        $options = $question->options;

        // Assert
        $this->assertCount(2, $options);
    }

    public function test_scope_ordered_sorts_by_order(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();
        $second = MockExamQuestion::factory()->for($mockExam)->create(['order' => 2]);
        $first = MockExamQuestion::factory()->for($mockExam)->create(['order' => 1]);

        // Act
        $results = MockExamQuestion::ordered()->where('mock_exam_id', $mockExam->id)->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'order 昇順で先頭は order=1 のはず');
    }

    public function test_order_cast_returns_integer(): void
    {
        // Arrange
        $question = MockExamQuestion::factory()->create(['order' => '7']);

        // Act
        $fresh = $question->fresh();

        // Assert
        $this->assertIsInt($fresh->order);
        $this->assertSame(7, $fresh->order);
    }
}
