<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * PlanResource の toArray 構造を検証する Unit テスト。
 * 招待モーダル / プラン延長モーダルの select 描画に必要なフィールド (status / status_label 含む) を網羅する。
 */
class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_array_exposes_plan_fields(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create([
            'name' => 'Premium Plan',
            'duration_days' => 180,
        ]);

        // Act
        $array = (new PlanResource($plan))->toArray(Request::create('/'));

        // Assert
        $this->assertSame($plan->id, $array['id']);
        $this->assertSame('Premium Plan', $array['name']);
        $this->assertSame(180, $array['duration_days']);
        $this->assertSame($plan->status->value, $array['status']);
        $this->assertArrayHasKey('status_label', $array, 'select 表示用に status_label を含むはず');
    }
}
