<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Exceptions\Content\QuestionCategoryMismatchException;
use App\Exceptions\Content\QuestionInvalidOptionsException;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Section 紐づき問題の更新ユースケース。
 *
 * - section_id は不可変(Section を跨いだ移動は新規作成で対応する)
 * - category_id 変更時は所属資格との一致を検証
 * - options が含まれる場合は delete-and-insert で同期し、is_correct=true がちょうど 1 件であることを検証
 *
 * @see \App\UseCases\SectionQuestion\StoreAction
 */
final class UpdateAction
{
    /**
     * @param array{
     *     body: string,
     *     explanation?: ?string,
     *     category_id: string,
     *     options?: array<int, array{body: string, is_correct: bool}>
     * } $validated
     *
     * @throws QuestionCategoryMismatchException
     * @throws QuestionInvalidOptionsException
     */
    public function __invoke(SectionQuestion $question, array $validated): SectionQuestion
    {
        $question->loadMissing('section.chapter.part');
        $certificationId = $question->section->chapter->part->certification_id;

        if ($validated['category_id'] !== $question->category_id) {
            $category = QuestionCategory::find($validated['category_id']);
            if ($category === null || $category->certification_id !== $certificationId) {
                throw new QuestionCategoryMismatchException;
            }
        }

        if (array_key_exists('options', $validated)) {
            $correctCount = collect($validated['options'])->where('is_correct', true)->count();
            if ($correctCount !== 1) {
                throw new QuestionInvalidOptionsException;
            }
        }

        return DB::transaction(function () use ($question, $validated) {
            $question->update([
                'body' => $validated['body'],
                'explanation' => $validated['explanation'] ?? null,
                'category_id' => $validated['category_id'],
            ]);

            if (array_key_exists('options', $validated)) {
                $question->options()->delete();
                foreach ($validated['options'] as $idx => $opt) {
                    $question->options()->create([
                        'body' => $opt['body'],
                        'is_correct' => (bool) $opt['is_correct'],
                        'order' => $idx,
                    ]);
                }
            }

            return $question->fresh(['options', 'category']);
        });
    }
}
