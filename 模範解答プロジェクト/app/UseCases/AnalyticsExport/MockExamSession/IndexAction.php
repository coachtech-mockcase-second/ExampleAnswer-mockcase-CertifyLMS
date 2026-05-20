<?php

declare(strict_types=1);

namespace App\UseCases\AnalyticsExport\MockExamSession;

use App\Http\Controllers\Api\MockExamSessionController;
use App\Models\MockExamSession;
use App\Services\WeaknessAnalysisService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 運用エクスポート API の模試セッション一覧取得ユースケース。
 *
 * `?mock_exam_id` / `?pass` / `?status` / `?from` / `?to` でフィルタしてページングした後、
 * WeaknessAnalysisService の `batchHeatmap` で `category_breakdown` (graded のみ非空) を一括算出する。
 * 算出結果は各 Session に attribute として注入し、Resource が直接参照する。
 *
 * @see MockExamSessionController::index()
 */
final class IndexAction
{
    public function __construct(
        private readonly WeaknessAnalysisService $weaknessService,
    ) {}

    /**
     * @param array{mock_exam_id?: string|null, pass?: string|null, status?: string|null, from?: string|null, to?: string|null, per_page?: int|null, page?: int|null} $validated
     * @param array<int, string> $includes  Eager Loading リレーション名 (camelCase)
     *
     * @return LengthAwarePaginator<MockExamSession>
     */
    public function __invoke(array $validated, array $includes): LengthAwarePaginator
    {
        $perPage = (int) ($validated['per_page'] ?? 100);

        $sessions = MockExamSession::query()
            ->whereNull('deleted_at')
            ->when(
                $validated['mock_exam_id'] ?? null,
                fn ($query, $mockExamId) => $query->where('mock_exam_id', $mockExamId),
            )
            ->when(
                ($validated['pass'] ?? null) !== null,
                fn ($query) => $query->where(
                    'pass',
                    filter_var($validated['pass'], FILTER_VALIDATE_BOOLEAN),
                ),
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when(
                $validated['from'] ?? null,
                fn ($query, $from) => $query->where('submitted_at', '>=', $from),
            )
            ->when(
                $validated['to'] ?? null,
                fn ($query, $to) => $query->where('submitted_at', '<=', $to.' 23:59:59'),
            )
            ->with($includes)
            ->orderBy('created_at')
            ->paginate($perPage);

        $heatmapMap = $this->weaknessService->batchHeatmap($sessions->getCollection());

        // バッチ集計結果を Resource から参照できるよう各 Model に attribute として注入する。
        $sessions->getCollection()->each(function (MockExamSession $session) use ($heatmapMap) {
            $session->setAttribute('analytics_category_breakdown', $heatmapMap[$session->id] ?? []);
        });

        return $sessions;
    }
}
