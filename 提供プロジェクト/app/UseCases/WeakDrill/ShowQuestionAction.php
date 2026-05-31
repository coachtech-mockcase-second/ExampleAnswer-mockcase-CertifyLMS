<?php

declare(strict_types=1);

namespace App\UseCases\WeakDrill;

use App\Enums\ContentStatus;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Exceptions\QuizAnswering\WeakDrillCategoryMismatchException;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAttempt;
use App\Models\User;

/**
 * 苦手分野ドリル経路の 1 問出題画面のデータをまとめる Action。
 *
 * カテゴリ × 資格 × 問題 の三重整合を検証し、次の問題 ID + 受講生本人の Attempt を返す。
 */
final class ShowQuestionAction
{
    /**
     * @return array{question: SectionQuestion, next_id: ?string, attempt: ?SectionQuestionAttempt}
     *
     * @throws WeakDrillCategoryMismatchException
     * @throws SectionQuestionUnavailableForAnswerException
     */
    public function __invoke(
        Enrollment $enrollment,
        QuestionCategory $category,
        SectionQuestion $question,
        User $student,
    ): array {
        if ($category->certification_id !== $enrollment->certification_id) {
            throw new WeakDrillCategoryMismatchException;
        }

        if ($question->category_id !== $category->id) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        $question->load([
            'options' => fn ($q) => $q->orderBy('order'),
            'category',
            'section.chapter.part',
        ]);

        $next = SectionQuestion::query()
            ->where('category_id', $category->id)
            ->where('status', ContentStatus::Published->value)
            ->where('order', '>', $question->order)
            ->whereHas(
                'section',
                fn ($q) => $q->where('status', ContentStatus::Published->value)
                    ->whereHas(
                        'chapter',
                        fn ($q2) => $q2->where('status', ContentStatus::Published->value)
                            ->whereHas('part', fn ($q3) => $q3->where('status', ContentStatus::Published->value)),
                    ),
            )
            ->orderBy('order')
            ->orderBy('id')
            ->first();

        $attempt = SectionQuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('section_question_id', $question->id)
            ->first();

        return [
            'question' => $question,
            'next_id' => $next?->id,
            'attempt' => $attempt,
        ];
    }
}
