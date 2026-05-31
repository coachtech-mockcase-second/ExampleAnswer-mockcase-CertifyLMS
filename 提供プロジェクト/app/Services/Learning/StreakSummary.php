<?php

declare(strict_types=1);

namespace App\Services\Learning;

use Carbon\CarbonImmutable;

/**
 * StreakService::calculate の戻り値 DTO。
 * 学習活動日 (DISTINCT DATE(learning_sessions.started_at)) ベースの連続学習日数を表す。
 *
 * - current_streak: 今日 (or 昨日まで) を含む連続学習日数。今日も昨日も学習がない場合は 0
 * - longest_streak: 過去全期間における最長連続学習日数
 * - last_active_date: 直近の学習活動日 (null = 過去に学習履歴なし)
 */
final readonly class StreakSummary
{
    public function __construct(
        public int $currentStreak,
        public int $longestStreak,
        public ?CarbonImmutable $lastActiveDate,
    ) {}
}
