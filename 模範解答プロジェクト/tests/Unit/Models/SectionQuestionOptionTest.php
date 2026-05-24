<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SectionQuestionOption モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 1 リレーション (sectionQuestion) + 1 scope (ordered) + 2 cast (is_correct bool / order int) を網羅する。
 * factory state: correct() / wrong() で is_correct を制御する。
 */
class SectionQuestionOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_question_relation_returns_parent_question(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        $option = SectionQuestionOption::factory()->for($question, 'sectionQuestion')->create();

        // Act
        $parent = $option->sectionQuestion;

        // Assert
        $this->assertTrue($parent->is($question));
    }

    public function test_scope_ordered_sorts_by_order(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        $second = SectionQuestionOption::factory()->for($question, 'sectionQuestion')->create(['order' => 2]);
        $first = SectionQuestionOption::factory()->for($question, 'sectionQuestion')->create(['order' => 1]);

        // Act
        $results = SectionQuestionOption::ordered()->where('section_question_id', $question->id)->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'order 昇順で先頭は order=1 のはず');
    }

    public function test_is_correct_cast_returns_boolean(): void
    {
        // Arrange
        $option = SectionQuestionOption::factory()->correct()->create();

        // Act
        $fresh = $option->fresh();

        // Assert
        $this->assertIsBool($fresh->is_correct, 'is_correct は boolean にキャストされるはず');
        $this->assertTrue($fresh->is_correct);
    }

    public function test_order_cast_returns_integer(): void
    {
        // Arrange
        $option = SectionQuestionOption::factory()->create(['order' => '3']);

        // Act
        $fresh = $option->fresh();

        // Assert
        $this->assertIsInt($fresh->order);
        $this->assertSame(3, $fresh->order);
    }
}
