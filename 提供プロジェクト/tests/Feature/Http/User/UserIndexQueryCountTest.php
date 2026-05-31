<?php

declare(strict_types=1);

namespace Tests\Feature\Http\User;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 管理者ユーザー一覧 (`admin.users.index`) の N+1 非回帰を検証する Feature テスト。
 * プラン情報を一括取得することで、受講者件数を増やしても発行クエリ数がほぼ増えない
 * (各行のプラン名参照で遅延ロードが発火しない) ことを担保する。
 */
class UserIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_user_count(): void
    {
        // Arrange: 管理者 + 各自プランを持つ受講生 2 名 (基準)
        $admin = User::factory()->admin()->create();
        $this->createStudentsWithPlans(2);

        // Act: 基準のクエリ数を計測 → 受講生を 10 名追加して再計測 (1 ページに収まる件数)
        $baseline = $this->countQueriesFor(
            fn () => $this->actingAs($admin)->get(route('admin.users.index'))
        );
        $this->createStudentsWithPlans(10);
        $scaled = $this->countQueriesFor(
            fn () => $this->actingAs($admin)->get(route('admin.users.index'))
        );

        // Assert: 受講生が増えても発行クエリ数はほぼ一定 (N+1 なら件数分増える)
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "ユーザー管理一覧で N+1 が再発している (基準 {$baseline} → 増加後 {$scaled})。プラン情報を Eager Loading で一括取得しているか確認",
        );
    }

    private function createStudentsWithPlans(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->student()->inProgress()
                ->withPlan(Plan::factory()->published()->create())
                ->create();
        }
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
