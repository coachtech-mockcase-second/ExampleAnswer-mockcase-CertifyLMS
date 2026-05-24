<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SectionProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SectionProgress モデルのリレーション・Cast を検証する Unit テスト。
 * 2 リレーション (enrollment / section) + 1 cast (completed_at datetime) を網羅する。
 * Section の完了状態を Enrollment 単位で記録する pivot 相当のモデル。
 */
class SectionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_owner_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $progress = SectionProgress::factory()->for($enrollment)->create();

        // Act
        $owner = $progress->enrollment;

        // Assert
        $this->assertTrue($owner->is($enrollment));
    }

    public function test_section_relation_returns_target_section(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $progress = SectionProgress::factory()->for($section)->create();

        // Act
        $target = $progress->section;

        // Assert
        $this->assertTrue($target->is($section));
    }

    public function test_completed_at_cast_returns_carbon(): void
    {
        // Arrange
        $progress = SectionProgress::factory()->create([
            'completed_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $progress->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->completed_at, 'completed_at は Carbon datetime にキャストされるはず');
    }
}
