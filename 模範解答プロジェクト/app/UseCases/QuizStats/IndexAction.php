<?php

declare(strict_types=1);

namespace App\UseCases\QuizStats;

use App\Models\Enrollment;
use App\Models\SectionQuestionAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の SectionQuestion サマリ一覧を取得する Action。
 *
 * 当該 Enrollment の資格に属する SectionQuestion についての累計 Attempt を、
 * Section / Category / 最新正誤 でフィルタ + 並び替えしてページネーションで返す。
 */
final class IndexAction
{
    /**
     * @param  array{section_id?: ?string, category_id?: ?string, last_is_correct?: ?bool, sort?: ?string}  $filters
     * @return LengthAwarePaginator<SectionQuestionAttempt>
     */
    public function __invoke(Enrollment $enrollment, array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'recent';

        $query = SectionQuestionAttempt::query()
            ->where('user_id', $enrollment->user_id)
            ->whereHas(
                'sectionQuestion.section.chapter.part',
                fn ($q) => $q->where('certification_id', $enrollment->certification_id),
            )
            ->when($filters['section_id'] ?? null, fn ($q, $v) => $q->whereHas(
                'sectionQuestion',
                fn ($sq) => $sq->where('section_id', $v),
            ))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->whereHas(
                'sectionQuestion',
                fn ($sq) => $sq->where('category_id', $v),
            ))
            ->when(
                isset($filters['last_is_correct']) && $filters['last_is_correct'] !== null,
                fn ($q) => $q->where('last_is_correct', $filters['last_is_correct']),
            )
            ->with([
                'sectionQuestion.section.chapter.part',
                'sectionQuestion.category',
            ]);

        $this->applySort($query, $sort);

        return $query->paginate(20)->withQueryString();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<SectionQuestionAttempt>  $query
     */
    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sort): void
    {
        match ($sort) {
            'accuracy_asc' => $query
                ->orderBy(DB::raw('CASE WHEN attempt_count = 0 THEN 1 ELSE 0 END'))
                ->orderBy(DB::raw('correct_count * 1.0 / NULLIF(attempt_count, 0)'))
                ->orderByDesc('last_answered_at'),
            'accuracy_desc' => $query
                ->orderByDesc(DB::raw('correct_count * 1.0 / NULLIF(attempt_count, 0)'))
                ->orderByDesc('last_answered_at'),
            'attempts_desc' => $query
                ->orderByDesc('attempt_count')
                ->orderByDesc('last_answered_at'),
            default => $query->orderByDesc('last_answered_at'),
        };
    }
}
