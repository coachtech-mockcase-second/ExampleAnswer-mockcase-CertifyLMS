<?php

declare(strict_types=1);

namespace App\UseCases\AdminMockExamSession;

use App\Enums\PassProbabilityBand;
use App\Models\MockExamSession;
use App\Services\CategoryHeatmapCell;
use App\Services\WeaknessAnalysisService;
use Illuminate\Support\Collection;

/**
 * admin / coach 用の模試受験セッション詳細を取得するユースケース。
 *
 * 受講生の結果ビューと同じ素材(ヒートマップ + 合格可能性バンド) を組み立てて返す。
 * Service の DI 解決は ServiceProvider 経由(WeaknessAnalysisServiceContract → WeaknessAnalysisService)。
 *
 * @return array{session: MockExamSession, heatmap: Collection<int, CategoryHeatmapCell>, passProbabilityBand: PassProbabilityBand}
 */
final class ShowAction
{
    public function __construct(
        private readonly WeaknessAnalysisService $weaknessAnalysis,
    ) {}

    /**
     * @return array{
     *     session: MockExamSession,
     *     heatmap: Collection,
     *     passProbabilityBand: PassProbabilityBand,
     * }
     */
    public function __invoke(MockExamSession $session): array
    {
        $session->load([
            'mockExam.certification',
            'enrollment.user',
            'user',
            'answers.mockExamQuestion.category',
            'answers.selectedOption',
        ]);

        return [
            'session' => $session,
            'heatmap' => $this->weaknessAnalysis->getHeatmap($session),
            'passProbabilityBand' => $this->weaknessAnalysis->getPassProbabilityBand($session->enrollment),
        ];
    }
}
