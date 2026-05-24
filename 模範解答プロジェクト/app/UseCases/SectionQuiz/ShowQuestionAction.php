<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuiz;

use App\Enums\ContentStatus;
use App\Exceptions\QuizAnswering\SectionQuestionUnavailableForAnswerException;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAttempt;
use App\Models\User;

/**
 * Section 経路の 1 問出題画面のデータをまとめる Action。
 *
 * 親 Section との整合性検証 + 次の問題 ID の解決 + 受講生本人の Attempt 取得を担う。
 */
final class ShowQuestionAction
{
    /**
     * @return array{question: SectionQuestion, next_id: ?string, attempt: ?SectionQuestionAttempt}
     *
     * @throws SectionQuestionUnavailableForAnswerException
     */
    public function __invoke(Section $section, SectionQuestion $question, User $student): array
    {
        if ($question->section_id !== $section->id) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        if ($question->status !== ContentStatus::Published) {
            throw new SectionQuestionUnavailableForAnswerException;
        }

        $question->load(['options' => fn ($q) => $q->orderBy('order'), 'category']);

        $next = SectionQuestion::query()
            ->where('section_id', $section->id)
            ->where('status', ContentStatus::Published->value)
            ->where('order', '>', $question->order)
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
