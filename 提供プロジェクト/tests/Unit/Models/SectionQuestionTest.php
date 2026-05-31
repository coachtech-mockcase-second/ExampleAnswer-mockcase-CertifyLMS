<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ContentStatus;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SectionQuestion モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 5 リレーション (section / category / options / sectionQuestionAnswers / sectionQuestionAttempts) +
 * 主要 scope 3 (published / ofSection / byCategory) + 3 cast (status / order / published_at) を網羅する。
 */
class SectionQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_relation_returns_parent_section(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $question = SectionQuestion::factory()->for($section)->create();

        // Act
        $parent = $question->section;

        // Assert
        $this->assertTrue($parent->is($section));
    }

    public function test_category_relation_returns_question_category(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $question = SectionQuestion::factory()->for($category, 'category')->create();

        // Act
        $assigned = $question->category;

        // Assert
        $this->assertTrue($assigned->is($category));
    }

    public function test_options_relation_returns_attached_options(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        SectionQuestionOption::factory()->for($question, 'sectionQuestion')->correct()->create();
        SectionQuestionOption::factory()->for($question, 'sectionQuestion')->wrong()->create();

        // Act
        $options = $question->options;

        // Assert
        $this->assertCount(2, $options);
    }

    public function test_scope_published_filters_only_published(): void
    {
        // Arrange
        SectionQuestion::factory()->draft()->create();
        $published = SectionQuestion::factory()->published()->create();

        // Act
        $results = SectionQuestion::published()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_of_section_filters_by_section_id(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $matching = SectionQuestion::factory()->for($section)->published()->create();
        SectionQuestion::factory()->published()->create();

        // Act
        $results = SectionQuestion::ofSection($section->id)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($matching));
    }

    public function test_scope_by_category_filters_by_category_id(): void
    {
        // Arrange
        $category = QuestionCategory::factory()->create();
        $matching = SectionQuestion::factory()->for($category, 'category')->published()->create();
        SectionQuestion::factory()->published()->create();

        // Act
        $results = SectionQuestion::byCategory($category->id)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($matching));
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->draft()->create();

        // Act
        $fresh = $question->fresh();

        // Assert
        $this->assertInstanceOf(ContentStatus::class, $fresh->status);
        $this->assertSame(ContentStatus::Draft, $fresh->status);
    }

    public function test_published_at_cast_returns_carbon(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();

        // Act
        $fresh = $question->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->published_at);
    }
}
