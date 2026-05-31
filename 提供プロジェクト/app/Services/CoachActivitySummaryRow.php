<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * `CoachActivityService::summarize` の戻り値 1 行を表す不変値オブジェクト。
 * コーチ稼働サマリ(完了数 / キャンセル数 / 平均メモ文字数)を 1 つの構造体で持ち運ぶ。
 */
final readonly class CoachActivitySummaryRow
{
    public function __construct(
        public User $coach,
        public int $completedCount,
        public int $canceledCount,
        public ?int $averageMemoLength,
    ) {}
}
