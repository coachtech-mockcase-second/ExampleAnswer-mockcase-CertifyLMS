<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Services\Learning\ProgressSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 学習進捗率 (Section→Chapter→Part→資格 完了率) の集計を提供する Service。
 *
 * - `summarize(Enrollment)`: 4 階層の完了数・総数・比率を持つ ProgressSummary を返す (画面 1 件用)
 * - `sectionRatio(Enrollment, Part|Chapter|null)`: 指定スコープの Section 単位完了率 (進捗バー用)
 * - `batchCalculate(Collection<Enrollment>)`: 複数 Enrollment の overall_completion_ratio をまとめて返す
 *
 * クエリ時集計のみで内部キャッシュは持たない (RAM / Redis のキャッシュ管理コストが集計コストを上回らない)。
 */
final class ProgressService
{
    public function summarize(Enrollment $enrollment): ProgressSummary
    {
        $totals = $this->fetchSectionTotals($enrollment);

        $partsTotal = Part::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
            ->count();

        $chaptersTotal = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value)
                    ->whereNull('deleted_at');
            })
            ->where('status', ContentStatus::Published->value)
            ->whereNull('deleted_at')
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

    public function sectionRatio(Enrollment $enrollment, Part|Chapter|null $scope = null): float
    {
        $query = DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id)
                    ->whereNull('section_progresses.deleted_at');
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->whereNull('parts.deleted_at')
            ->where('chapters.status', ContentStatus::Published->value)
            ->whereNull('chapters.deleted_at')
            ->where('sections.status', ContentStatus::Published->value)
            ->whereNull('sections.deleted_at');

        if ($scope instanceof Part) {
            $query->where('parts.id', $scope->id);
        } elseif ($scope instanceof Chapter) {
            $query->where('chapters.id', $scope->id);
        }

        $row = $query->selectRaw('COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->first();

        if ($row === null || (int) $row->total === 0) {
            return 0.0;
        }

        return round((int) $row->done / (int) $row->total, 4);
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<string, float>
     */
    public function batchCalculate(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($enrollments as $enrollment) {
            $result[$enrollment->id] = $this->sectionRatio($enrollment);
        }

        return $result;
    }

    private function fetchSectionTotals(Enrollment $enrollment): object
    {
        return DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id)
                    ->whereNull('section_progresses.deleted_at');
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->whereNull('parts.deleted_at')
            ->where('chapters.status', ContentStatus::Published->value)
            ->whereNull('chapters.deleted_at')
            ->where('sections.status', ContentStatus::Published->value)
            ->whereNull('sections.deleted_at')
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
                    ->where('sections.status', ContentStatus::Published->value)
                    ->whereNull('sections.deleted_at');
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id)
                    ->whereNull('section_progresses.deleted_at');
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->whereNull('parts.deleted_at')
            ->where('chapters.status', ContentStatus::Published->value)
            ->whereNull('chapters.deleted_at')
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
                    ->where('chapters.status', ContentStatus::Published->value)
                    ->whereNull('chapters.deleted_at');
            })
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value)
                    ->whereNull('sections.deleted_at');
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id)
                    ->whereNull('section_progresses.deleted_at');
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->whereNull('parts.deleted_at')
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
