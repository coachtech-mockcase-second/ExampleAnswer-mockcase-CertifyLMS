<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuiz;

use App\Enums\ContentStatus;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionAttempt;
use App\Models\SectionQuestionOption;
use App\Models\User;

/**
 * Section 経路の結果画面のデータをまとめる Action。
 *
 * 解答 / 問題 / Section の整合性検証 + 次の問題 ID の解決 + 正答 Option + 累計 Attempt 取得を担う。
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
     * @throws SectionQuestionUnavailableForAnswerException
     */
    public function __invoke(
        Section $section,
        SectionQuestion $question,
        SectionQuestionAnswer $answer,
        User $student,
    ): array {
        if ($question->section_id !== $section->id) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        if ($answer->section_question_id !== $question->id) {
            abort(404);
        }

        $question->load(['options' => fn ($q) => $q->orderBy('order'), 'category']);

        $correctOption = $question->options->firstWhere('is_correct', true);

        $attempt = SectionQuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('section_question_id', $question->id)
            ->first();

        $next = SectionQuestion::query()
            ->where('section_id', $section->id)
            ->where('status', ContentStatus::Published->value)
            ->where('order', '>', $question->order)
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
