<?php

declare(strict_types=1);

namespace App\UseCases\MockExamQuestion;

use App\Exceptions\MockExam\QuestionCategoryMismatchException;
use App\Exceptions\MockExam\QuestionInvalidOptionsException;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

/**
 * 模試問題を新規作成するユースケース。
 *
 * - category_id が親 MockExam の Certification 配下にあることを検証
 * - is_correct = true の選択肢がちょうど 1 件であることを検証
 * - `lockForUpdate` で MAX(order) を取得し末尾に挿入
 * - mock_exam_questions と mock_exam_question_options を 1 トランザクションで INSERT
 */
final class StoreAction
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
    public function __invoke(MockExam $mockExam, array $validated): MockExamQuestion
    {
        $categoryBelongsToCertification = QuestionCategory::query()
            ->where('id', $validated['category_id'])
            ->where('certification_id', $mockExam->certification_id)
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

        return DB::transaction(function () use ($mockExam, $validated) {
            $maxOrder = MockExamQuestion::query()
                ->where('mock_exam_id', $mockExam->id)
                ->lockForUpdate()
                ->max('order');

            $question = MockExamQuestion::create([
                'mock_exam_id' => $mockExam->id,
                'category_id' => $validated['category_id'],
                'body' => $validated['body'],
                'explanation' => $validated['explanation'] ?? null,
                'order' => $maxOrder === null ? 0 : $maxOrder + 1,
            ]);

            foreach ($validated['options'] as $option) {
                $question->options()->create([
                    'body' => $option['body'],
                    'is_correct' => (bool) $option['is_correct'],
                    'order' => $option['order'],
                ]);
            }

            return $question->load('options');
        });
    }
}
