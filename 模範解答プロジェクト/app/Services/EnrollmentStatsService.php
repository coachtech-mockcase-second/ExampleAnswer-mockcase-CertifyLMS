<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;

/**
 * Enrollment 集計を提供する Service。admin ダッシュボード KPI で利用される。
 *
 * 集計対象は SoftDelete 除外。paused 集計は採用しない(3 値モデル)。
 */
final class EnrollmentStatsService
{
    /**
     * @return array{learning: int, passed: int, failed: int, total: int}
     */
    public function adminKpi(): array
    {
        $counts = Enrollment::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $learning = (int) ($counts[EnrollmentStatus::Learning->value] ?? 0);
        $passed = (int) ($counts[EnrollmentStatus::Passed->value] ?? 0);
        $failed = (int) ($counts[EnrollmentStatus::Failed->value] ?? 0);

        return [
            'learning' => $learning,
            'passed' => $passed,
            'failed' => $failed,
            'total' => $learning + $passed + $failed,
        ];
    }

    /**
     * 資格別の受講生数(status 別の内訳付き)。
     *
     * @return array<string, array{learning: int, passed: int, failed: int}>  キーは certification_id
     */
    public function perCertification(): array
    {
        $rows = Enrollment::query()
            ->selectRaw('certification_id, status, COUNT(*) as cnt')
            ->groupBy('certification_id', 'status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $certId = (string) $row->certification_id;
            $result[$certId] ??= ['learning' => 0, 'passed' => 0, 'failed' => 0];
            $result[$certId][$row->status] = (int) $row->cnt;
        }

        return $result;
    }
}
