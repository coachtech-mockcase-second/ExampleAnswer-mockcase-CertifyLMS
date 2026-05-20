<?php

declare(strict_types=1);

namespace App\UseCases\AnalyticsExport\Enrollment;

use App\Http\Controllers\Api\EnrollmentController;
use App\Models\Enrollment;
use App\Services\LastActivityService;
use App\Services\ProgressService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 運用エクスポート API の受講登録一覧取得ユースケース。
 *
 * `?status` / `?certification_id` / `?current_term` でフィルタしてページングした後、
 * ProgressService と LastActivityService の `batch*` で `progress_rate` / `last_activity_at` を
 * 一括算出する (N+1 回避)。算出結果は各 Enrollment に attribute として注入し、Resource が直接参照する。
 *
 * @see EnrollmentController::index()
 */
final class IndexAction
{
    public function __construct(
        private readonly ProgressService $progressService,
        private readonly LastActivityService $lastActivityService,
    ) {}

    /**
     * @param array{status?: string|null, certification_id?: string|null, current_term?: string|null, per_page?: int|null, page?: int|null} $validated
     * @param array<int, string> $includes Eager Loading リレーション名 (camelCase)
     *
     * @return LengthAwarePaginator<Enrollment>
     */
    public function __invoke(array $validated, array $includes): LengthAwarePaginator
    {
        $perPage = (int) ($validated['per_page'] ?? 100);

        $enrollments = Enrollment::query()
            ->whereNull('deleted_at')
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when(
                $validated['certification_id'] ?? null,
                fn ($query, $certificationId) => $query->where('certification_id', $certificationId),
            )
            ->when(
                $validated['current_term'] ?? null,
                fn ($query, $term) => $query->where('current_term', $term),
            )
            ->with($includes)
            ->orderBy('created_at')
            ->paginate($perPage);

        $progressMap = $this->progressService->batchCalculate($enrollments->getCollection());
        $activityMap = $this->lastActivityService->batchLastActivityFor($enrollments->getCollection());

        // バッチ集計結果を Resource から参照できるよう各 Model に attribute として注入する。
        $enrollments->getCollection()->each(function (Enrollment $enrollment) use ($progressMap, $activityMap) {
            $enrollment->setAttribute('analytics_progress_rate', $progressMap[$enrollment->id] ?? null);
            $enrollment->setAttribute('analytics_last_activity_at', $activityMap[$enrollment->id] ?? null);
        });

        return $enrollments;
    }
}
