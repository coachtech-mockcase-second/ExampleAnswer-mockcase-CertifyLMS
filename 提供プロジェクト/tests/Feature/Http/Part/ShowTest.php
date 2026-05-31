<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Part;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 詳細画面(PartController::show)の検証。
 * Part 詳細に表示される配下 Chapter 一覧が並び順(order 昇順)で返ることを確認する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_chapters_are_listed_in_order_ascending(): void
    {
        // Arrange: order を登録順とずらして Chapter を作成
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->published()->create();
        Chapter::factory()->forPart($part)->published()->create(['order' => 3]);
        Chapter::factory()->forPart($part)->published()->create(['order' => 1]);
        Chapter::factory()->forPart($part)->published()->create(['order' => 2]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.parts.show', $part));

        // Assert: 登録順(3,1,2)ではなく order 昇順(1,2,3)で並ぶ
        $response->assertOk();
        $this->assertSame(
            [1, 2, 3],
            $response->viewData('part')->chapters->pluck('order')->all(),
            'Part 詳細の Chapter 一覧は order 昇順で並ぶはず(登録順ではない)',
        );
    }
}
