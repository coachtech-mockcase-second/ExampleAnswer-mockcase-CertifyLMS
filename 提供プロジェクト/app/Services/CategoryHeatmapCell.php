<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 模試結果画面に表示する分野別ヒートマップの 1 セルを表す DTO。
 *
 * 分野(QuestionCategory) ごとの正答数 / 総問題数 / 正答率を保持し、Blade はこの値を直接表示する。
 * Service 内で `MockExamAnswer.is_correct` を `JOIN mock_exam_questions JOIN question_categories` で集計して構築する。
 */
final readonly class CategoryHeatmapCell
{
    public function __construct(
        public string $categoryId,
        public string $categoryName,
        public int $totalCount,
        public int $correctCount,
        public float $correctRate,
    ) {}
}
