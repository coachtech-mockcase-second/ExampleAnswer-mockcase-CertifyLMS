<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\User;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 面談空き枠集計 `MeetingAvailabilityService::slotsForCertification` の N+1 非回帰を検証する Unit テスト。
 * 担当コーチを増やしても、コーチ集合・空き枠・既存予約を一括取得することで発行クエリ数がほぼ増えない
 * (コーチごとの個別クエリが発火しない) ことを担保する。
 * Google 連携なしコーチのみで構成し、外部 API 呼び出しを発生させない。
 */
class MeetingAvailabilityQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_query_count_does_not_grow_with_coach_count(): void
    {
        // Arrange: 公開資格 + Google 未連携コーチ 2 名 (各 月曜 09-12 に空き枠) = 基準
        $certification = Certification::factory()->published()->create();
        $date = Carbon::parse('2026-06-01'); // Monday (dayOfWeek=1)
        $this->attachCoachesWithAvailability($certification, 2);

        // Act: 基準のクエリ数を計測 → コーチを 4 名追加して再計測
        $baseline = $this->countQueriesFor(
            fn () => app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date)
        );
        $this->attachCoachesWithAvailability($certification, 4);
        $scaled = $this->countQueriesFor(
            fn () => app(MeetingAvailabilityService::class)->slotsForCertification($certification, $date)
        );

        // Assert: コーチが増えても発行クエリ数はほぼ一定 (N+1 ならコーチ数分増える)
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "空き枠集計で N+1 が再発している (基準 {$baseline} → 増加後 {$scaled})。コーチ集合・空き枠・予約を whereIn で一括取得しているか確認",
        );
    }

    private function attachCoachesWithAvailability(Certification $certification, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $coach = User::factory()->coach()->create();
            $certification->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => User::factory()->admin()->create()->id,
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);
            CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
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
