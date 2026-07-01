<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LearningSession;
use App\Models\User;
use App\Services\Learning\LearningCalendar;
use Carbon\CarbonImmutable;

/**
 * 受講生ダッシュボードの学習カレンダー (GitHub 風の日別学習時間ヒートマップ) のデータを供給する Service。
 *
 * フロントの草グリッド描画 (resources/js/dashboard/learning-calendar.js) に渡すための
 * 「日別学習時間マップ (Y-m-d → 分)」と「今月の学習時間合計 (分)」を組み立てる。
 * 濃淡レベルの離散化・グリッド構築は JS 側で行うため、本 Service は集計済みの素データのみを返す。
 * 日付グルーピングのタイムゾーンは連続学習日数の集計と揃えて config('app.timezone')。
 *
 * 受講生ダッシュボードの Action テストで Mockery 経由 mock するため final は付けない。
 */
class LearningCalendarService
{
    public function build(User $user): LearningCalendar
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $months = (int) config('learning.calendar.months', 4);

        $today = CarbonImmutable::now($timezone)->startOfDay();
        // 草グリッドは「今月を含む直近 N ヶ月」。グリッド端 (週頭) の取りこぼし防止に 1 週前から取得する。
        $rangeStart = $today->subMonthsNoOverflow($months - 1)->startOfMonth()->subDays(7);

        $minutesByDate = $this->sumMinutesByDate($user, $rangeStart, $timezone);

        return new LearningCalendar(
            daysMap: $minutesByDate,
            monthTotalMinutes: $this->currentMonthMinutes($minutesByDate, $today),
            today: $today->toDateString(),
        );
    }

    /**
     * 取得起点以降の学習セッションを、活動日 (Y-m-d) ごとの学習時間合計 (分) にまとめる。
     *
     * @return array<string, int> 例: ['2026-05-01' => 60]
     */
    private function sumMinutesByDate(User $user, CarbonImmutable $start, string $timezone): array
    {
        /** @var array<string, int> $secondsByDate */
        $secondsByDate = [];

        LearningSession::query()
            ->forUser($user)
            ->whereNotNull('started_at')
            ->where('started_at', '>=', $start)
            ->get(['started_at', 'duration_seconds'])
            ->each(function (LearningSession $session) use (&$secondsByDate, $timezone): void {
                $date = CarbonImmutable::instance($session->started_at)
                    ->setTimezone($timezone)
                    ->toDateString();
                $secondsByDate[$date] = ($secondsByDate[$date] ?? 0) + (int) $session->duration_seconds;
            });

        return array_map(static fn (int $seconds): int => intdiv($seconds, 60), $secondsByDate);
    }

    /**
     * 日別マップから今日が属する月の学習時間合計 (分) を取り出す。
     *
     * @param array<string, int> $minutesByDate
     */
    private function currentMonthMinutes(array $minutesByDate, CarbonImmutable $today): int
    {
        $prefix = $today->format('Y-m');
        $sum = 0;
        foreach ($minutesByDate as $date => $minutes) {
            if (str_starts_with($date, $prefix)) {
                $sum += $minutes;
            }
        }

        return $sum;
    }
}
