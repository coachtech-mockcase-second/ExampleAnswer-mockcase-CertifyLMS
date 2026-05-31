<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment 集計を提供する Service。admin ダッシュボード KPI で利用される。
 *
 * 全体 KPI(adminKpi)と資格別修了率(completionRateByCertification)は全 enrollment を走査する重い集計のため、
 * 集計を所有する本 Service が Cache::remember で一定時間キャッシュする(ダッシュボード UseCase は薄く保つ)。
 * 受講状態の遷移時に EnrollmentStatusChangeService がキャッシュを無効化し、それ以外の変化(新規受講登録 /
 * 資格公開)は TTL 失効で取り込む。
 *
 * 集計対象は SoftDelete 除外。paused 集計は採用しない(3 値モデル)。
 * 受講生ダッシュボードの Action / Controller テストで Mockery 経由 mock するため `final` は付けない。
 */
class EnrollmentStatsService
{
    /**
     * 全体 KPI(learning / passed / failed 件数 + 資格別内訳)を返す。重い集計のため一定時間キャッシュする。
     *
     * @return array{learning_count: int, passed_count: int, failed_count: int, total: int, by_certification: array<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int}>}
     */
    public function adminKpi(): array
    {
        return Cache::remember(
            config('dashboard.admin_kpi_cache_key'),
            config('dashboard.admin_stats_cache_ttl'),
            fn (): array => $this->computeAdminKpi(),
        );
    }

    /**
     * 資格別の受講生数(status 別の内訳付き)。
     *
     * @return array<string, array{learning: int, passed: int, failed: int}> キーは certification_id
     */
    public function perCertification(): array
    {
        $rows = DB::table('enrollments')
            ->whereNull('deleted_at')
            ->selectRaw('certification_id, status, COUNT(*) as cnt')
            ->groupBy('certification_id', 'status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $certId = (string) $row->certification_id;
            $result[$certId] ??= ['learning' => 0, 'passed' => 0, 'failed' => 0];
            $result[$certId][(string) $row->status] = (int) $row->cnt;
        }

        return $result;
    }

    /**
     * 資格別の修了率(passed / 全件)を Collection で返す。重い集計のため一定時間キャッシュする。
     * 0 件の資格は除外する(0 % 表示は意味がないため)。
     * 一覧は受講生数(total)の多い順、上位 10 件まで。
     *
     * @return Collection<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int, completion_rate: float}>
     */
    public function completionRateByCertification(): Collection
    {
        return Cache::remember(
            config('dashboard.admin_completion_rate_cache_key'),
            config('dashboard.admin_stats_cache_ttl'),
            fn (): Collection => $this->computeCompletionRateByCertification(),
        );
    }

    /**
     * adminKpi のキャッシュ未ヒット時に実行する集計本体。
     *
     * @return array{learning_count: int, passed_count: int, failed_count: int, total: int, by_certification: array<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int}>}
     */
    private function computeAdminKpi(): array
    {
        $counts = DB::table('enrollments')
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $learning = (int) ($counts[EnrollmentStatus::Learning->value] ?? 0);
        $passed = (int) ($counts[EnrollmentStatus::Passed->value] ?? 0);
        $failed = (int) ($counts[EnrollmentStatus::Failed->value] ?? 0);

        return [
            'learning_count' => $learning,
            'passed_count' => $passed,
            'failed_count' => $failed,
            'total' => $learning + $passed + $failed,
            'by_certification' => $this->byCertification(),
        ];
    }

    /**
     * completionRateByCertification のキャッシュ未ヒット時に実行する集計本体。
     *
     * @return Collection<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int, completion_rate: float}>
     */
    private function computeCompletionRateByCertification(): Collection
    {
        return collect($this->byCertification())
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->map(function (array $row): array {
                $row['completion_rate'] = round($row['passed'] / $row['total'], 4);

                return $row;
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * 資格別の集計を「資格 ID + 名前 + status 別件数」の配列で返す内部ヘルパー。
     *
     * @return array<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int}>
     */
    private function byCertification(): array
    {
        $rows = DB::table('enrollments')
            ->join('certifications', 'enrollments.certification_id', '=', 'certifications.id')
            ->whereNull('enrollments.deleted_at')
            ->selectRaw('enrollments.certification_id, certifications.name as certification_name, enrollments.status, COUNT(*) as cnt')
            ->groupBy('enrollments.certification_id', 'certifications.name', 'enrollments.status')
            ->get();

        $byCertification = [];
        foreach ($rows as $row) {
            $certId = (string) $row->certification_id;
            $byCertification[$certId] ??= [
                'certification_id' => $certId,
                'certification_name' => (string) $row->certification_name,
                'learning' => 0,
                'passed' => 0,
                'failed' => 0,
                'total' => 0,
            ];
            $byCertification[$certId][(string) $row->status] = (int) $row->cnt;
            $byCertification[$certId]['total'] += (int) $row->cnt;
        }

        $list = array_values($byCertification);
        usort($list, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $list;
    }
}
