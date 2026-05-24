<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Part モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 2 リレーション (certification / chapters) + 2 scope (published / ordered) + 3 cast (status / order / published_at) を網羅する。
 */
class PartTest extends TestCase
{
    use RefreshDatabase;

    public function test_certification_relation_returns_parent_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->create();

        // Act
        $parent = $part->certification;

        // Assert
        $this->assertTrue($parent->is($cert), '親 certification と part->certification は一致するはず');
    }

    public function test_chapters_relation_returns_attached_chapters(): void
    {
        // Arrange
        $part = Part::factory()->create();
        Chapter::factory()->for($part)->create();
        Chapter::factory()->for($part)->create();
        Chapter::factory()->create();

        // Act
        $chapters = $part->chapters;

        // Assert
        $this->assertCount(2, $chapters, '対象 part の chapter のみが取得されるはず');
    }

    public function test_scope_published_filters_only_published_status(): void
    {
        // Arrange
        Part::factory()->draft()->create();
        $published = Part::factory()->published()->create();

        // Act
        $results = Part::published()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_ordered_sorts_by_order_column(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $first = Part::factory()->for($cert)->create(['order' => 1]);
        $third = Part::factory()->for($cert)->create(['order' => 3]);
        $second = Part::factory()->for($cert)->create(['order' => 2]);

        // Act
        $results = Part::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'order 昇順で先頭は order=1 のはず');
        $this->assertTrue($results->last()->is($third), 'order 昇順で末尾は order=3 のはず');
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $part = Part::factory()->published()->create();

        // Act
        $fresh = $part->fresh();

        // Assert
        $this->assertInstanceOf(ContentStatus::class, $fresh->status, 'status は ContentStatus enum にキャストされるはず');
        $this->assertSame(ContentStatus::Published, $fresh->status);
    }

    public function test_order_and_published_at_casts(): void
    {
        // Arrange
        $part = Part::factory()->published()->create(['order' => '5']);

        // Act
        $fresh = $part->fresh();

        // Assert
        $this->assertIsInt($fresh->order, 'order は integer にキャストされるはず');
        $this->assertSame(5, $fresh->order);
        $this->assertInstanceOf(Carbon::class, $fresh->published_at, 'published_at は Carbon datetime にキャストされるはず');
    }
}
