<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Chapter モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 2 リレーション (part / sections) + 2 scope (published / ordered) + 3 cast (status / order / published_at) を網羅する。
 */
class ChapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_part_relation_returns_parent_part(): void
    {
        // Arrange
        $part = Part::factory()->published()->create();
        $chapter = Chapter::factory()->for($part)->create();

        // Act
        $parent = $chapter->part;

        // Assert
        $this->assertTrue($parent->is($part));
    }

    public function test_sections_relation_returns_attached_sections(): void
    {
        // Arrange
        $chapter = Chapter::factory()->create();
        Section::factory()->for($chapter)->create();
        Section::factory()->for($chapter)->create();
        Section::factory()->create();

        // Act
        $sections = $chapter->sections;

        // Assert
        $this->assertCount(2, $sections, '対象 chapter の section のみが取得されるはず');
    }

    public function test_scope_published_filters_only_published_status(): void
    {
        // Arrange: scopePublished は親 Part も Published であることを要求する
        $publishedPart = Part::factory()->published()->create();
        Chapter::factory()->draft()->create();
        $published = Chapter::factory()->for($publishedPart)->published()->create();

        // Act
        $results = Chapter::published()->get();

        // Assert
        $this->assertCount(1, $results, 'status=Published かつ親 Part も Published の chapter のみ抽出されるはず');
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_ordered_sorts_by_order_column(): void
    {
        // Arrange
        $part = Part::factory()->published()->create();
        $second = Chapter::factory()->for($part)->create(['order' => 2]);
        $first = Chapter::factory()->for($part)->create(['order' => 1]);

        // Act
        $results = Chapter::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'order 昇順で先頭は order=1 のはず');
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $chapter = Chapter::factory()->draft()->create();

        // Act
        $fresh = $chapter->fresh();

        // Assert
        $this->assertInstanceOf(ContentStatus::class, $fresh->status);
        $this->assertSame(ContentStatus::Draft, $fresh->status);
    }

    public function test_published_at_cast_returns_carbon(): void
    {
        // Arrange
        $chapter = Chapter::factory()->published()->create();

        // Act
        $fresh = $chapter->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->published_at);
    }
}
