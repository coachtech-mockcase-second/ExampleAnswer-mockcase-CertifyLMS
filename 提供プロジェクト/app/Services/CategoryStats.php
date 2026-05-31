<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SectionQuestionAttemptStatsService::byCategory の戻り値要素。
 * QuestionCategory 1 件分の集計値を保持する。
 *
 * accuracy は totalAttempts が 0 件のとき null。
 */
final readonly class CategoryStats
{
    public function __construct(
        public string $categoryId,
        public int $questionsAttempted,
        public int $totalAttempts,
        public int $totalCorrect,
        public ?float $accuracy,
    ) {}
}
