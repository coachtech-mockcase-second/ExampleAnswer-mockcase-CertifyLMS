<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Enums\ContentStatus;
use App\Exceptions\Content\QuestionCategoryMismatchException;
use App\Exceptions\Content\QuestionInvalidOptionsException;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Section 紐づき問題の新規作成ユースケース。
 *
 * - category の所属資格が Section の所属資格と一致することを検証(不一致時 QuestionCategoryMismatchException)
 * - is_correct=true がちょうど 1 件であることを検証(違反時 QuestionInvalidOptionsException)
 * - lockForUpdate で同一 Section 配下の MAX(order) を取得し、競合下でも一意な order を採番
 * - status=Draft 固定で INSERT、選択肢は一括 INSERT(同一トランザクション)
 */
final class StoreAction
{
    /**
     * @param array{
     *     body: string,
     *     explanation?: ?string,
     *     category_id: string,
     *     options: array<int, array{body: string, is_correct: bool}>
     * } $validated
     *
     * @throws QuestionCategoryMismatchException
     * @throws QuestionInvalidOptionsException
     */
    public function __invoke(Section $section, array $validated): SectionQuestion
    {
        $section->loadMissing('chapter.part');
        $certificationId = $section->chapter->part->certification_id;

        $category = QuestionCategory::find($validated['category_id']);
        if ($category === null || $category->certification_id !== $certificationId) {
            throw new QuestionCategoryMismatchException;
        }

        $correctCount = collect($validated['options'])->where('is_correct', true)->count();
        if ($correctCount !== 1) {
            throw new QuestionInvalidOptionsException;
        }

        return DB::transaction(function () use ($section, $validated) {
            $maxOrder = SectionQuestion::where('section_id', $section->id)
                ->lockForUpdate()
                ->max('order');

            $question = $section->questions()->create([
                'category_id' => $validated['category_id'],
                'body' => $validated['body'],
                'explanation' => $validated['explanation'] ?? null,
                'order' => ($maxOrder ?? -1) + 1,
                'status' => ContentStatus::Draft->value,
                'published_at' => null,
            ]);

            foreach ($validated['options'] as $idx => $opt) {
                $question->options()->create([
                    'body' => $opt['body'],
                    'is_correct' => (bool) $opt['is_correct'],
                    'order' => $idx,
                ]);
            }

            return $question->fresh(['options', 'category']);
        });
    }
}
