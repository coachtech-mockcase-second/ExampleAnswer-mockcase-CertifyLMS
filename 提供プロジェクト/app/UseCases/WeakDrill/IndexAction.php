<?php

declare(strict_types=1);

namespace App\UseCases\WeakDrill;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Services\CategoryStats;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\SectionQuestionAttemptStatsService;
use Illuminate\Support\Collection;

/**
 * 苦手分野ドリルのカテゴリ一覧画面のデータをまとめる Action。
 *
 * - 対象資格配下の QuestionCategory(sort_order ASC)
 * - 各カテゴリの公開済 SectionQuestion 件数
 * - 受講生の SectionQuestionAttempt から導出した正答率(CategoryStats)
 * - 模試 Feature からのおすすめバッジ判定(未バインド時は NullObject で空)
 */
final class IndexAction
{
    public function __construct(
        private readonly SectionQuestionAttemptStatsService $stats,
        private readonly WeaknessAnalysisServiceContract $weakness,
    ) {}

    /**
     * @return array{
     *     categories: Collection<int, QuestionCategory>,
     *     statsById: array<string, CategoryStats>,
     *     weakCategoryIds: array<int, string>
     * }
     */
    public function __invoke(Enrollment $enrollment): array
    {
        $categories = QuestionCategory::query()
            ->where('certification_id', $enrollment->certification_id)
            ->ordered()
            ->withCount([
                'sectionQuestions as published_section_questions_count' => fn ($q) => $q
                    ->where('status', ContentStatus::Published->value)
                    ->whereHas(
                        'section',
                        fn ($sq) => $sq->where('status', ContentStatus::Published->value)
                            ->whereHas(
                                'chapter',
                                fn ($cq) => $cq->where('status', ContentStatus::Published->value)
                                    ->whereHas('part', fn ($pq) => $pq->where('status', ContentStatus::Published->value)),
                            ),
                    ),
            ])
            ->get();

        $statsById = $this->stats->byCategory($enrollment)
            ->keyBy(fn ($stat) => $stat->categoryId)
            ->all();

        $weakCategoryIds = $this->weakness->getWeakCategories($enrollment)
            ->pluck('id')
            ->all();

        return [
            'categories' => $categories,
            'statsById' => $statsById,
            'weakCategoryIds' => $weakCategoryIds,
        ];
    }
}
