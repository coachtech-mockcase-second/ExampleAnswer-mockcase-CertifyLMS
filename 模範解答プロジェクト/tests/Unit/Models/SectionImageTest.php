<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Section;
use App\Models\SectionImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SectionImage モデルのリレーション・Cast を検証する Unit テスト。
 * 1 リレーション (section) + 1 cast (size_bytes integer) を網羅する。
 */
class SectionImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_relation_returns_parent_section(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $image = SectionImage::factory()->for($section)->create();

        // Act
        $parent = $image->section;

        // Assert
        $this->assertTrue($parent->is($section));
    }

    public function test_size_bytes_cast_returns_integer(): void
    {
        // Arrange
        $image = SectionImage::factory()->create(['size_bytes' => '12345']);

        // Act
        $fresh = $image->fresh();

        // Assert
        $this->assertIsInt($fresh->size_bytes, 'size_bytes は integer にキャストされるはず');
        $this->assertSame(12345, $fresh->size_bytes);
    }
}
