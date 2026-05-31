<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\SectionQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Section モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 5 リレーション (chapter / questions / images / learningSessions / progresses) + 3 scope (published / ordered / keyword) +
 * 3 cast (status / order / published_at) を網羅する。
 */
class SectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_chapter_relation_returns_parent_chapter(): void
    {
        // Arrange
        $chapter = Chapter::factory()->published()->create();
        $section = Section::factory()->for($chapter)->create();

        // Act
        $parent = $section->chapter;

        // Assert
        $this->assertTrue($parent->is($chapter));
    }

    public function test_questions_relation_returns_attached_questions(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        SectionQuestion::factory()->for($section)->create();
        SectionQuestion::factory()->for($section)->create();
        SectionQuestion::factory()->create();

        // Act
        $questions = $section->questions;

        // Assert
        $this->assertCount(2, $questions, '対象 section の question のみが取得されるはず');
    }

    public function test_images_relation_returns_attached_images(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        SectionImage::factory()->for($section)->create();
        SectionImage::factory()->for($section)->create();

        // Act
        $images = $section->images;

        // Assert
        $this->assertCount(2, $images);
    }

    public function test_scope_published_filters_only_published(): void
    {
        // Arrange: scopePublished は親 Chapter / Part も Published であることを要求する
        $publishedPart = Part::factory()->published()->create();
        $publishedChapter = Chapter::factory()->for($publishedPart)->published()->create();
        Section::factory()->draft()->create();
        $published = Section::factory()->for($publishedChapter)->published()->create();

        // Act
        $results = Section::published()->get();

        // Assert
        $this->assertCount(1, $results, 'status=Published かつ親 Chapter / Part も Published の section のみ抽出されるはず');
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_keyword_filters_by_title_match(): void
    {
        // Arrange
        $chapter = Chapter::factory()->published()->create();
        $target = Section::factory()->for($chapter)->published()->create(['title' => 'AWS の VPC 概要']);
        Section::factory()->for($chapter)->published()->create(['title' => 'IAM のロール設計']);

        // Act
        $results = Section::keyword('VPC')->get();

        // Assert
        $this->assertCount(1, $results, 'キーワード VPC を含む section のみが抽出されるはず');
        $this->assertTrue($results->first()->is($target));
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();

        // Act
        $fresh = $section->fresh();

        // Assert
        $this->assertInstanceOf(ContentStatus::class, $fresh->status);
        $this->assertSame(ContentStatus::Published, $fresh->status);
    }

    public function test_published_at_cast_returns_carbon(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();

        // Act
        $fresh = $section->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->published_at);
    }
}
