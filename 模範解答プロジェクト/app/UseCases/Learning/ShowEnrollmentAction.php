<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use App\Services\LearningHourTargetService;
use App\Services\ProgressService;
use App\Services\SectionQuestionScoreService;
use App\Services\StreakService;

/**
 * /learning/enrollments/{enrollment} (2 階層目、教材 Part 一覧) のデータを準備する Action。
 *
 * 共通サマリカード(進捗ゲージ / ストリーク / 学習時間目標)はタブの外で常に表示する。
 * タブは教材 / 演習問題の 2 タブで切替:
 * - contents (default): Part → Chapter → Section の階層一覧 + Section 読了状況
 * - quizzes: Section 別演習スコア一覧 + タブ内右上に 苦手分野ドリル / 解答履歴 / 問題別サマリへの動線
 */
final class ShowEnrollmentAction
{
    public function __construct(
        private readonly ProgressService $progressService,
        private readonly StreakService $streakService,
        private readonly LearningHourTargetService $hourTargetService,
        private readonly SectionQuestionScoreService $scoreService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Enrollment $enrollment, string $tab = 'contents'): array
    {
        $enrollment->loadMissing(['certification', 'user', 'learningHourTarget']);

        $parts = $enrollment->certification
            ?->parts()
            ->where('status', ContentStatus::Published->value)
            ->ordered()
            ->with([
                'chapters' => fn ($query) => $query
                    ->where('status', ContentStatus::Published->value)
                    ->ordered()
                    ->with([
                        'sections' => fn ($q) => $q
                            ->where('status', ContentStatus::Published->value)
                            ->ordered(),
                    ]),
            ])
            ->get() ?? collect();

        $sectionProgresses = $enrollment->sectionProgresses()
            ->whereNull('deleted_at')
            ->pluck('section_id')
            ->all();

        $quizScoreSummaries = $tab === 'quizzes'
            ? $this->scoreService->batchSummarize($enrollment->user, $enrollment)
            : collect();

        return [
            'enrollment' => $enrollment,
            'parts' => $parts,
            'tab' => $tab,
            'completedSectionIds' => $sectionProgresses,
            'progress' => $this->progressService->summarize($enrollment),
            'streak' => $this->streakService->calculate($enrollment->user),
            'hourTargetSummary' => $this->hourTargetService->compute($enrollment),
            'quizScoreSummaries' => $quizScoreSummaries,
        ];
    }
}
