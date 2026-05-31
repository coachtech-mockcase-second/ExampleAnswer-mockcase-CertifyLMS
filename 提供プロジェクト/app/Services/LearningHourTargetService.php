<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Services\Learning\LearningHourTargetSummary;
use Carbon\CarbonImmutable;

/**
 * 学習時間目標の進捗計算を提供する Service。
 *
 * target_total_hours と LearningSession の SUM(duration_seconds) から、
 * 残り時間 / 残り日数 / 日次推奨ペース / 進捗率を導出する。target 未設定でも累計時間は返す。
 */
final class LearningHourTargetService
{
    public function compute(Enrollment $enrollment): LearningHourTargetSummary
    {
        $enrollment->loadMissing('learningHourTarget');

        $target = $enrollment->learningHourTarget;
        $targetHours = $target?->target_total_hours;

        $studiedSeconds = (int) LearningSession::query()
            ->forEnrollment($enrollment)
            ->closed()
            ->sum('duration_seconds');

        $studiedHours = round($studiedSeconds / 3600, 2);

        $remainingHours = null;
        if ($targetHours !== null) {
            $remainingHours = max(0.0, round($targetHours - $studiedHours, 2));
        }

        $remainingDays = $this->computeRemainingDays($enrollment->exam_date);

        $dailyRecommended = null;
        if ($remainingHours !== null && $remainingDays !== null && $remainingDays > 0) {
            $dailyRecommended = round($remainingHours / $remainingDays, 2);
        }

        $progressRatio = null;
        if ($targetHours !== null && $targetHours > 0) {
            $progressRatio = min(1.0, round($studiedHours / $targetHours, 4));
        }

        return new LearningHourTargetSummary(
            targetTotalHours: $targetHours,
            studiedTotalSeconds: $studiedSeconds,
            studiedTotalHours: $studiedHours,
            remainingHours: $remainingHours,
            remainingDays: $remainingDays,
            dailyRecommendedHours: $dailyRecommended,
            progressRatio: $progressRatio,
        );
    }

    private function computeRemainingDays(?\DateTimeInterface $examDate): ?int
    {
        if ($examDate === null) {
            return null;
        }

        $today = CarbonImmutable::today();
        $diff = (int) $today->diffInDays($examDate, false);

        return $diff > 0 ? $diff : null;
    }
}
