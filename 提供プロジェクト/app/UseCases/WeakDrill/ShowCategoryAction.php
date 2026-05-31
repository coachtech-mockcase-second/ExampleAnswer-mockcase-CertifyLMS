<?php

declare(strict_types=1);

namespace App\UseCases\WeakDrill;

use App\Enums\ContentStatus;
use App\Exceptions\QuizAnswering\WeakDrillCategoryMismatchException;
use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 苦手分野ドリルのカテゴリ別 SectionQuestion リスト画面のデータをまとめる Action。
 *
 * カテゴリ × 資格の整合性を検証し、cascade visibility を満たす SectionQuestion のみを返す。
 * 模試問題は出題対象に含めず、SectionQuestion のみを返す。
 */
final class ShowCategoryAction
{
    /**
     * @return Collection<int, SectionQuestion>
     *
     * @throws WeakDrillCategoryMismatchException
     */
    public function __invoke(Enrollment $enrollment, QuestionCategory $category, User $student): Collection
    {
        if ($category->certification_id !== $enrollment->certification_id) {
            throw new WeakDrillCategoryMismatchException;
        }

        return SectionQuestion::query()
            ->where('category_id', $category->id)
            ->where('status', ContentStatus::Published->value)
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
            ->with([
                'section.chapter.part',
                'category',
                'options' => fn ($q) => $q->orderBy('order'),
                'sectionQuestionAttempts' => fn ($q) => $q->where('user_id', $student->id),
            ])
            ->get();
    }
}
