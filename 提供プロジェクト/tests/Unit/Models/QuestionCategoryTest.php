<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QuestionCategory モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (certification / sectionQuestions / mockExamQuestions) + 1 scope (ordered) + 1 cast (sort_order integer) を網羅する。
 * 出題分野マスタを資格単位で持つモデル。
 */
class QuestionCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_certification_relation_returns_parent_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->for($cert)->create();

        // Act
        $parent = $category->certification;

        // Assert
        $this->assertTrue($parent->is($cert));
    }

    public function test_section_questions_relation_returns_attached_questions(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        SectionQuestion::factory()->for($category, 'category')->create();
        SectionQuestion::factory()->for($category, 'category')->create();
        SectionQuestion::factory()->create();

        // Act
        $questions = $category->sectionQuestions;

        // Assert
        $this->assertCount(2, $questions, '対象 category の section_questions のみが取得されるはず');
    }

    public function test_scope_ordered_sorts_by_sort_order(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $second = QuestionCategory::factory()->for($cert)->create(['sort_order' => 2]);
        $first = QuestionCategory::factory()->for($cert)->create(['sort_order' => 1]);

        // Act
        $results = QuestionCategory::ordered()->where('certification_id', $cert->id)->get();

        // Assert
        $this->assertTrue($results->first()->is($first));
    }

    public function test_sort_order_cast_returns_integer(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create(['sort_order' => '10']);

        // Act
        $fresh = $category->fresh();

        // Assert
        $this->assertIsInt($fresh->sort_order);
        $this->assertSame(10, $fresh->sort_order);
    }
}
