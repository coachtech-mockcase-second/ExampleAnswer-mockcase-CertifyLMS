<?php

declare(strict_types=1);

namespace App\UseCases\QuizHistory;

use App\Enums\AnswerSource;
use App\Models\Enrollment;
use App\Models\SectionQuestionAnswer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生の解答履歴一覧を取得する Action。
 *
 * Enrollment の資格に属する SectionQuestion への自身の解答に限定し、
 * Section / Category / 正誤 / 出題経路 でフィルタ可能。answered_at 降順 + 20 件ページネーション。
 */
final class IndexAction
{
    /**
     * @param array{section_id?: ?string, category_id?: ?string, is_correct?: ?bool, source?: ?string} $filters
     *
     * @return LengthAwarePaginator<SectionQuestionAnswer>
     */
    public function __invoke(Enrollment $enrollment, array $filters): LengthAwarePaginator
    {
        return SectionQuestionAnswer::query()
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
                isset($filters['is_correct']) && $filters['is_correct'] !== null,
                fn ($q) => $q->where('is_correct', $filters['is_correct']),
            )
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where(
                'source',
                $v instanceof AnswerSource ? $v->value : $v,
            ))
            ->with([
                'sectionQuestion.section.chapter.part',
                'sectionQuestion.category',
                'sectionQuestion.options',
            ])
            ->orderByDesc('answered_at')
            ->paginate(20)
            ->withQueryString();
    }
}
