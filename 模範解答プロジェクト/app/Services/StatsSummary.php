<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * 受講生 × 資格 単位の SectionQuestion 演習サマリ。
 *
 * - totalQuestionsAttempted: 解答した SectionQuestion のユニーク件数
 * - totalAttempts: 解答送信回数の総和
 * - totalCorrect: 正解した解答送信回数の総和
 * - overallAccuracy: totalCorrect / totalAttempts(0 件の場合は null)
 * - lastAnsweredAt: 最後に解答送信した時刻(0 件の場合は null)
 */
final readonly class StatsSummary
{
    public function __construct(
        public int $totalQuestionsAttempted,
        public int $totalAttempts,
        public int $totalCorrect,
        public ?float $overallAccuracy,
        public ?Carbon $lastAnsweredAt,
    ) {}
}
