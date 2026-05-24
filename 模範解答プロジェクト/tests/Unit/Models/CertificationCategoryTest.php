<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\CertificationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CertificationCategory モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 1 リレーション (certifications) + 1 scope (ordered) + 1 cast (sort_order integer) を網羅する。
 */
class CertificationCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_certifications_relation_returns_attached_certifications(): void
    {
        // Arrange
        $category = CertificationCategory::factory()->create();
        Certification::factory()->forCategory($category)->create();
        Certification::factory()->forCategory($category)->create();
        Certification::factory()->create();

        // Act
        $certifications = $category->certifications;

        // Assert
        $this->assertCount(2, $certifications, '対象 category の certification のみが取得されるはず');
    }

    public function test_scope_ordered_sorts_by_sort_order(): void
    {
        // Arrange
        $second = CertificationCategory::factory()->create(['sort_order' => 2]);
        $first = CertificationCategory::factory()->create(['sort_order' => 1]);

        // Act
        $results = CertificationCategory::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first), 'sort_order 昇順で先頭は sort_order=1 のはず');
    }

    public function test_sort_order_cast_returns_integer(): void
    {
        // Arrange
        $category = CertificationCategory::factory()->create(['sort_order' => '5']);

        // Act
        $fresh = $category->fresh();

        // Assert
        $this->assertIsInt($fresh->sort_order);
        $this->assertSame(5, $fresh->sort_order);
    }
}
