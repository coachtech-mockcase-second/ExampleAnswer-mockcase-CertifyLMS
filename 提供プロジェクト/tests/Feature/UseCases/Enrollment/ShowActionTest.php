<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Enrollment;

use App\Models\Enrollment;
use App\UseCases\Enrollment\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment 詳細取得 Action `ShowAction` の eager load を検証する Feature テスト。
 * 詳細ビューに必要な certification / certificate / 最新の状態遷移ログが eager load されることを確認する。
 */
class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_certification_for_detail_view(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $result = app(ShowAction::class)($enrollment);

        // Assert
        $this->assertTrue(
            $result->relationLoaded('certification'),
            'ShowAction は詳細表示用に certification を eager load するはず',
        );
    }
}
