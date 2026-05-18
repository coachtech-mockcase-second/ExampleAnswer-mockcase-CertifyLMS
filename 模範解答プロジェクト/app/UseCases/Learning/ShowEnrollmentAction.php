<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use App\Services\LearningHourTargetService;
use App\Services\ProgressService;
use App\Services\StreakService;

/**
 * /learning/enrollments/{enrollment} (2 階層目、教材 Part 一覧) のデータを準備する Action。
 *
 * 教材タブと演習問題タブの 2 タブで構成され、URL クエリ `?tab=` で切替。
 * - contents (default): Part 一覧 + 進捗ゲージ + ストリーク + 学習時間目標サマリ
 * - quizzes: 同 Part → Chapter → Section 階層に各 Section の演習問題リンク + スコアサマリ
 *
 * 演習問題タブのスコアサマリは Section 紐づき問題演習側の Service が未実装でも UI が破綻しないよう、
 * 教材タブ側では空 array を返し、問題演習機能の実装後に SectionQuestionScoreService::batchSummarize を呼ぶ。
 */
final class ShowEnrollmentAction
{
    public function __construct(
        private readonly ProgressService $progressService,
        private readonly StreakService $streakService,
        private readonly LearningHourTargetService $hourTargetService,
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

        return [
            'enrollment' => $enrollment,
            'parts' => $parts,
            'tab' => $tab,
            'completedSectionIds' => $sectionProgresses,
            'progress' => $this->progressService->summarize($enrollment),
            'streak' => $this->streakService->calculate($enrollment->user),
            'hourTargetSummary' => $this->hourTargetService->compute($enrollment),
            'quizScoreSummaries' => [],
        ];
    }
}
