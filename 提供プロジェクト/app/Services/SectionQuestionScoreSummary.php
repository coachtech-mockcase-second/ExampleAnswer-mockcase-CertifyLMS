<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Section 単位の演習スコアサマリ。教材画面の演習タブ / Section 詳細画面の演習リンクに表示する。
 *
 * - attemptCount: 当該 Section 配下の SectionQuestion に対する解答送信回数の総和
 * - bestScore / latestScore: その Section を「全問解答」した 1 ラウンドあたりの正解数(0〜出題数)。
 *   まだラウンドを完了していない場合は null
 * - latestAnsweredAt: その Section の最後の解答日時(0 件なら null)
 * - accuracyRate: その Section 全体の正答率(0〜1、0 件なら null)
 */
final readonly class SectionQuestionScoreSummary
{
    public function __construct(
        public int $attemptCount,
        public ?int $bestScore,
        public ?int $latestScore,
        public ?Carbon $latestAnsweredAt,
        public ?float $accuracyRate,
    ) {}

    public static function empty(): self
    {
        return new self(
            attemptCount: 0,
            bestScore: null,
            latestScore: null,
            latestAnsweredAt: null,
            accuracyRate: null,
        );
    }
}
