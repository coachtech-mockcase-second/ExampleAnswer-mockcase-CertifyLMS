<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chapter;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chapter 詳細画面(ChapterController::show)の検証。
 * Chapter 詳細に表示される配下 Section 一覧が並び順(order 昇順)で返ることを確認する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_are_listed_in_order_ascending(): void
    {
        // Arrange: order を登録順とずらして Section を作成
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        Section::factory()->forChapter($chapter)->published()->create(['order' => 3]);
        Section::factory()->forChapter($chapter)->published()->create(['order' => 1]);
        Section::factory()->forChapter($chapter)->published()->create(['order' => 2]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.chapters.show', $chapter));

        // Assert: 登録順(3,1,2)ではなく order 昇順(1,2,3)で並ぶ
        $response->assertOk();
        $this->assertSame(
            [1, 2, 3],
            $response->viewData('chapter')->sections->pluck('order')->all(),
            'Chapter 詳細の Section 一覧は order 昇順で並ぶはず(登録順ではない)',
        );
    }
}
