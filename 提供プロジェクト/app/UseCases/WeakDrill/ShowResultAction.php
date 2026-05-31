<?php

declare(strict_types=1);

namespace App\UseCases\WeakDrill;

use App\Enums\ContentStatus;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Exceptions\QuizAnswering\WeakDrillCategoryMismatchException;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\SectionQuestionOption;
use App\Models\User;

/**
 * 苦手分野ドリル経路の結果画面のデータをまとめる Action。
 *
 * Enrollment / Category / Question / Answer の四者整合を検証し、次の問題 ID + 正答 Option + 累計 Attempt を返す。
 */
final class ShowResultAction
{
    /**
     * @return array{
     *     question: SectionQuestion,
     *     answer: SectionQuestionAnswer,
     *     correct_option: ?SectionQuestionOption,
     *     attempt: ?SectionQuestionAttempt,
     *     next_id: ?string
     * }
     *
     * @throws WeakDrillCategoryMismatchException
     * @throws SectionQuestionUnavailableForAnswerException
     */
    public function __invoke(
        Enrollment $enrollment,
        QuestionCategory $category,
        SectionQuestion $question,
        SectionQuestionAnswer $answer,
        User $student,
    ): array {
        if ($category->certification_id !== $enrollment->certification_id) {
            throw new WeakDrillCategoryMismatchException;
        }

        if ($question->category_id !== $category->id) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        if ($answer->section_question_id !== $question->id) {
            abort(404);
        }

        $question->load([
            'options' => fn ($q) => $q->orderBy('order'),
            'category',
            'section.chapter.part',
        ]);

        $correctOption = $question->options->firstWhere('is_correct', true);

        $attempt = SectionQuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('section_question_id', $question->id)
            ->first();

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

        return [
            'question' => $question,
            'answer' => $answer,
            'correct_option' => $correctOption,
            'attempt' => $attempt,
            'next_id' => $next?->id,
        ];
    }
}
