<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * コーチごとの面談稼働サマリ(完了 / キャンセル / 平均メモ文字数)を集計する Service。
 *
 * 指定期間に絞った直近の活動を `CoachActivitySummaryRow` の Collection で返し、
 * 必要な側(admin 用集計画面 / 個別管理画面 / バッチレポート等)が後段で表示形式を組み立てる。
 */
final class CoachActivityService
{
    /**
     * 指定期間内のコーチ別サマリを返す。期間未指定時は直近 30 日。
     *
     * @return Collection<int, CoachActivitySummaryRow>
     */
    public function summarize(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subDays(30);
        $to ??= now();

        $coaches = User::query()->where('role', UserRole::Coach)->get();
        if ($coaches->isEmpty()) {
            return collect();
        }

        $meetings = Meeting::query()
            ->whereIn('coach_id', $coaches->pluck('id')->all())
            ->whereBetween('scheduled_at', [$from, $to])
            ->with('meetingMemo')
            ->get();

        return $coaches->map(function (User $coach) use ($meetings) {
            $forCoach = $meetings->where('coach_id', $coach->id);

            $completed = $forCoach->where('status', MeetingStatus::Completed)->count();
            $canceled = $forCoach->where('status', MeetingStatus::Canceled)->count();

            $memoLengths = $forCoach
                ->map(fn (Meeting $m) => $m->meetingMemo?->body !== null ? mb_strlen($m->meetingMemo->body) : null)
                ->filter()
                ->values();

            $avgMemoLength = $memoLengths->isEmpty()
                ? null
                : (int) round($memoLengths->avg());

            return new CoachActivitySummaryRow(
                coach: $coach,
                completedCount: $completed,
                canceledCount: $canceled,
                averageMemoLength: $avgMemoLength,
            );
        })->values();
    }
}
