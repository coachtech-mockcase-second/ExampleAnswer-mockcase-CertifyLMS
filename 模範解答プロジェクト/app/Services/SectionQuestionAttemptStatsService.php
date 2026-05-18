<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生 × 資格単位の SectionQuestion 演習集計を提供するステートレス Service。
 *
 * - summarize: 全体サマリ(累計試行回数 / 正答回数 / 正答率 / 最終解答日時)
 * - byCategory: QuestionCategory 単位の内訳
 * - recentAnswers: 直近の解答ログ
 *
 * 各メソッドは SectionQuestion → Section → Chapter → Part の certification_id が
 * Enrollment の certification_id と一致するレコードに限定し、他資格の解答混入を防ぐ。
 * キャッシュは持たない(都度 DB 集計)。
 */
final class SectionQuestionAttemptStatsService
{
    public function summarize(Enrollment $enrollment): StatsSummary
    {
        $aggregate = SectionQuestionAttempt::query()
            ->where('user_id', $enrollment->user_id)
            ->whereHas(
                'sectionQuestion.section.chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->selectRaw('COUNT(*) as questions_attempted, COALESCE(SUM(attempt_count), 0) as total_attempts, COALESCE(SUM(correct_count), 0) as total_correct, MAX(last_answered_at) as last_answered_at')
            ->first();

        $questionsAttempted = (int) ($aggregate?->questions_attempted ?? 0);
        $totalAttempts = (int) ($aggregate?->total_attempts ?? 0);
        $totalCorrect = (int) ($aggregate?->total_correct ?? 0);
        $lastAnsweredAtRaw = $aggregate?->last_answered_at;

        $accuracy = $totalAttempts > 0 ? $totalCorrect / $totalAttempts : null;
        $lastAnsweredAt = $lastAnsweredAtRaw !== null ? Carbon::parse($lastAnsweredAtRaw) : null;

        return new StatsSummary(
            totalQuestionsAttempted: $questionsAttempted,
            totalAttempts: $totalAttempts,
            totalCorrect: $totalCorrect,
            overallAccuracy: $accuracy,
            lastAnsweredAt: $lastAnsweredAt,
        );
    }

    /**
     * @return Collection<int, CategoryStats>
     */
    public function byCategory(Enrollment $enrollment): Collection
    {
        $rows = SectionQuestionAttempt::query()
            ->where('section_question_attempts.user_id', $enrollment->user_id)
            ->join('section_questions', 'section_questions.id', '=', 'section_question_attempts.section_question_id')
            ->join('sections', 'sections.id', '=', 'section_questions.section_id')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->where('parts.certification_id', $enrollment->certification_id)
            ->whereNull('section_question_attempts.deleted_at')
            ->whereNull('section_questions.deleted_at')
            ->whereNull('sections.deleted_at')
            ->whereNull('chapters.deleted_at')
            ->whereNull('parts.deleted_at')
            ->groupBy('section_questions.category_id')
            ->select([
                'section_questions.category_id as category_id',
                DB::raw('COUNT(*) as questions_attempted'),
                DB::raw('COALESCE(SUM(section_question_attempts.attempt_count), 0) as total_attempts'),
                DB::raw('COALESCE(SUM(section_question_attempts.correct_count), 0) as total_correct'),
            ])
            ->get();

        return $rows->map(function ($row): CategoryStats {
            $attempts = (int) $row->total_attempts;
            $correct = (int) $row->total_correct;

            return new CategoryStats(
                categoryId: (string) $row->category_id,
                questionsAttempted: (int) $row->questions_attempted,
                totalAttempts: $attempts,
                totalCorrect: $correct,
                accuracy: $attempts > 0 ? $correct / $attempts : null,
            );
        })->values();
    }

    /**
     * @return Collection<int, SectionQuestionAnswer>
     */
    public function recentAnswers(Enrollment $enrollment, int $limit = 5): Collection
    {
        return SectionQuestionAnswer::query()
            ->where('user_id', $enrollment->user_id)
            ->whereHas(
                'sectionQuestion.section.chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->with(['sectionQuestion.section', 'sectionQuestion.category'])
            ->orderByDesc('answered_at')
            ->limit($limit)
            ->get();
    }
}
