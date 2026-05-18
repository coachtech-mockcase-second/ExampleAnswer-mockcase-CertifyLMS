<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LearningSession;
use App\Models\User;
use App\Services\Learning\StreakSummary;
use Carbon\CarbonImmutable;

/**
 * 連続学習日数 (ストリーク) の計算を提供する Service。
 *
 * 「学習活動日」は `DISTINCT DATE(learning_sessions.started_at) WHERE user_id = ?` で定義する。
 * タイムゾーンは `config('app.timezone')` で日付グルーピング。
 */
final class StreakService
{
    public function calculate(User $user): StreakSummary
    {
        $timezone = (string) config('app.timezone', 'UTC');

        $sessions = LearningSession::query()
            ->forUser($user)
            ->whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->get(['started_at']);

        if ($sessions->isEmpty()) {
            return new StreakSummary(0, 0, null);
        }

        $dates = $sessions
            ->map(fn ($session) => CarbonImmutable::instance($session->started_at)
                ->setTimezone($timezone)
                ->startOfDay())
            ->unique(fn (CarbonImmutable $date) => $date->toDateString())
            ->values();

        $lastActive = $dates->first();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $current = $this->countCurrentStreak($dates, $today);
        $longest = $this->countLongestStreak($dates);

        return new StreakSummary($current, $longest, $lastActive);
    }

    /**
     * 直近の学習活動日を起点に連続日数を数える。
     * 今日 / 昨日が含まれていなければ current_streak は 0 (連続が切れた状態)。
     *
     * @param  \Illuminate\Support\Collection<int, CarbonImmutable>  $dates  Desc 順、重複なし
     */
    private function countCurrentStreak(\Illuminate\Support\Collection $dates, CarbonImmutable $today): int
    {
        $first = $dates->first();
        if ($first === null) {
            return 0;
        }

        $diffFromToday = (int) $today->diffInDays($first, false);
        if ($diffFromToday < -1) {
            return 0;
        }

        $streak = 1;
        $previous = $first;
        foreach ($dates->slice(1) as $date) {
            if ((int) $previous->diffInDays($date, false) === -1) {
                $streak++;
                $previous = $date;

                continue;
            }
            break;
        }

        return $streak;
    }

    /**
     * 過去全期間における最長連続学習日数を数える。
     *
     * @param  \Illuminate\Support\Collection<int, CarbonImmutable>  $dates  Desc 順、重複なし
     */
    private function countLongestStreak(\Illuminate\Support\Collection $dates): int
    {
        if ($dates->isEmpty()) {
            return 0;
        }

        $ordered = $dates->sort()->values();
        $longest = 1;
        $current = 1;
        $previous = $ordered->first();

        foreach ($ordered->slice(1) as $date) {
            if ((int) $previous->diffInDays($date, false) === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
            $previous = $date;
        }

        return $longest;
    }
}
