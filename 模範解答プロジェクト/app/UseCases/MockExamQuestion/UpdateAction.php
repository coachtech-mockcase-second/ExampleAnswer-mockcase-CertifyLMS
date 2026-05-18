<?php

declare(strict_types=1);

namespace App\UseCases\MockExamQuestion;

use App\Exceptions\MockExam\QuestionCategoryMismatchException;
use App\Exceptions\MockExam\QuestionInvalidOptionsException;
use App\Models\MockExamQuestion;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

/**
 * 模試問題を更新するユースケース。`mock_exam_id` は不可変。
 *
 * - body / explanation / category_id を UPDATE
 * - mock_exam_question_options は delete-and-insert で同期(過去 MockExamAnswer.selected_option_id は cascade null)
 */
final class UpdateAction
{
    /**
     * @param  array{
     *     body: string,
     *     explanation?: ?string,
     *     category_id: string,
     *     options: array<int, array{body: string, is_correct: bool, order: int}>,
     * }  $validated
     *
     * @throws QuestionCategoryMismatchException
     * @throws QuestionInvalidOptionsException
     */
    public function __invoke(MockExamQuestion $question, array $validated): MockExamQuestion
    {
        $categoryBelongsToCertification = QuestionCategory::query()
            ->where('id', $validated['category_id'])
            ->where('certification_id', $question->mockExam->certification_id)
            ->exists();

        if (! $categoryBelongsToCertification) {
            throw new QuestionCategoryMismatchException;
        }

        $correctCount = collect($validated['options'])
            ->filter(fn (array $option) => (bool) $option['is_correct'])
            ->count();

        if ($correctCount !== 1) {
            throw new QuestionInvalidOptionsException;
        }

        return DB::transaction(function () use ($question, $validated) {
            $question->update([
                'body' => $validated['body'],
                'explanation' => $validated['explanation'] ?? null,
                'category_id' => $validated['category_id'],
            ]);

            $question->options()->delete();

            foreach ($validated['options'] as $option) {
                $question->options()->create([
                    'body' => $option['body'],
                    'is_correct' => (bool) $option['is_correct'],
                    'order' => $option['order'],
                ]);
            }

            return $question->fresh(['options', 'mockExam']);
        });
    }
}
