<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Services\Learning\ProgressSummary;
use App\Services\LearningHourTargetService;
use App\Services\SectionQuestionScoreService;
use App\Services\StreakService;
use Illuminate\Support\Facades\DB;

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
            'progress' => $this->summarizeProgress($enrollment),
            'streak' => $this->streakService->calculate($enrollment->user),
            'hourTargetSummary' => $this->hourTargetService->compute($enrollment),
            'quizScoreSummaries' => $quizScoreSummaries,
        ];
    }

    /**
     * 学習進捗 (Section→Chapter→Part→資格 完了率) の 4 階層サマリを算出する。
     */
    private function summarizeProgress(Enrollment $enrollment): ProgressSummary
    {
        $totals = $this->fetchSectionTotals($enrollment);

        $partsTotal = Part::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->count();

        $chaptersTotal = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value);
            })
            ->where('status', ContentStatus::Published->value)
            ->count();

        $sectionsTotal = (int) $totals->sections_total;
        $sectionsCompleted = (int) $totals->sections_completed;
        $sectionRatio = $sectionsTotal === 0 ? 0.0 : round($sectionsCompleted / $sectionsTotal, 4);

        $chaptersCompleted = $this->countCompletedChapters($enrollment);
        $partsCompleted = $this->countCompletedParts($enrollment);

        $chapterRatio = $chaptersTotal === 0 ? 0.0 : round($chaptersCompleted / $chaptersTotal, 4);
        $partRatio = $partsTotal === 0 ? 0.0 : round($partsCompleted / $partsTotal, 4);

        return new ProgressSummary(
            sectionsTotal: $sectionsTotal,
            sectionsCompleted: $sectionsCompleted,
            sectionCompletionRatio: $sectionRatio,
            chaptersTotal: $chaptersTotal,
            chaptersCompleted: $chaptersCompleted,
            chapterCompletionRatio: $chapterRatio,
            partsTotal: $partsTotal,
            partsCompleted: $partsCompleted,
            partCompletionRatio: $partRatio,
            overallCompletionRatio: $sectionRatio,
        );
    }

    private function fetchSectionTotals(Enrollment $enrollment): object
    {
        return DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->selectRaw('COUNT(sections.id) AS sections_total, COUNT(section_progresses.id) AS sections_completed')
            ->first() ?? (object) ['sections_total' => 0, 'sections_completed' => 0];
    }

    private function countCompletedChapters(Enrollment $enrollment): int
    {
        // 公開済 Chapter のうち、配下の公開済 Section が全て読了済かを Chapter 単位で判定。
        $rows = DB::table('chapters')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->groupBy('chapters.id')
            ->selectRaw('chapters.id AS chapter_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }

    private function countCompletedParts(Enrollment $enrollment): int
    {
        $rows = DB::table('parts')
            ->leftJoin('chapters', function ($join) {
                $join->on('chapters.part_id', '=', 'parts.id')
                    ->where('chapters.status', ContentStatus::Published->value);
            })
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->groupBy('parts.id')
            ->selectRaw('parts.id AS part_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }
}
