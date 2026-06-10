<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 候補コーチ集合から、過去 30 日の completed 件数が最少のコーチを 1 名選出する Service。
 *
 * 自動コーチ割当の負荷分散ロジックを担う。予約確定処理が空き枠と空きコーチを抽出した後、
 * 本 Service の `leastLoadedCoach` で 1 名に絞り込む。同数の場合は ULID 昇順で先頭を選ぶことで決定論的に
 * 同じ結果を返す(race condition 時に同じ INSERT が走るのを抑止する効果も得る)。
 */
final class CoachMeetingLoadService
{
    /**
     * 候補集合の中から、過去 30 日の completed 数が最少のコーチを 1 名返す。
     * 集計クエリは引数集合の id を IN 句で渡す単発 GROUP BY で、0 件のコーチも候補対象に残す。
     *
     * @param Collection<int, User> $candidates 空き枠 ∩ 当該時刻に予約なし のコーチ集合(空でないこと)
     */
    public function leastLoadedCoach(Collection $candidates): User
    {
        $coachIds = $candidates->pluck('id')->all();

        /** @var array<string, int> $counts coach_id => completed_count */
        $counts = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->where('status', MeetingStatus::Completed->value)
            ->where('scheduled_at', '>', now()->subDays(30))
            ->select('coach_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('coach_id')
            ->pluck('cnt', 'coach_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // 第 1 キー: 過去 30 日 completed 数 / 第 2 キー: ULID 昇順 で安定ソートする
        return $candidates->sortBy([
            fn (User $a, User $b) => ($counts[$a->id] ?? 0) <=> ($counts[$b->id] ?? 0),
            fn (User $a, User $b) => strcmp($a->id, $b->id),
        ])->first();
    }
}
