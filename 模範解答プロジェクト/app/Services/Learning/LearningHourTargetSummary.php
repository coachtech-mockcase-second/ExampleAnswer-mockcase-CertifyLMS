<?php

declare(strict_types=1);

namespace App\Services\Learning;

/**
 * LearningHourTargetService::compute の戻り値 DTO。
 * 学習時間目標 (合計目標時間) と現在の学習時間累計から、残り時間 / 残り日数 / 推奨日次ペース / 進捗率を導出する。
 *
 * - target_total_hours: 設定された目標時間 (null = 未設定)
 * - studied_total_seconds: 学習セッションの SUM(duration_seconds) (closed のみ)
 * - studied_total_hours: 上記を時間換算 (小数 2 桁)
 * - remaining_hours: target - studied (負値は 0 にクランプ)
 * - remaining_days: 試験日までの残り日数 (exam_date 未設定 / 過去日 の場合 null)
 * - daily_recommended_hours: remaining_hours / remaining_days (remaining_days が null / 0 の場合 null)
 * - progress_ratio: studied_total_hours / target_total_hours (上限 1.0、未設定時 null)
 */
final readonly class LearningHourTargetSummary
{
    public function __construct(
        public ?int $targetTotalHours,
        public int $studiedTotalSeconds,
        public float $studiedTotalHours,
        public ?float $remainingHours,
        public ?int $remainingDays,
        public ?float $dailyRecommendedHours,
        public ?float $progressRatio,
    ) {}
}
