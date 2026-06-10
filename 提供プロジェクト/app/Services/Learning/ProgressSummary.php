<?php

declare(strict_types=1);

namespace App\Services\Learning;

/**
 * 学習進捗集計の戻り値 DTO。
 * Section / Chapter / Part / 資格 4 階層の完了数・総数・比率を保持する。
 * overall_completion_ratio は Section 単位の比率(教材最小単位の進捗が最も実態に近いため)。
 */
final readonly class ProgressSummary
{
    public function __construct(
        public int $sectionsTotal,
        public int $sectionsCompleted,
        public float $sectionCompletionRatio,
        public int $chaptersTotal,
        public int $chaptersCompleted,
        public float $chapterCompletionRatio,
        public int $partsTotal,
        public int $partsCompleted,
        public float $partCompletionRatio,
        public float $overallCompletionRatio,
    ) {}
}
