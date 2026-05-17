<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

/**
 * QuestionCategory(出題分野マスタ) の更新ユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, slug: string, sort_order?: ?int, description?: ?string} $validated QuestionCategory/UpdateRequest::rules() で検証済
     */
    public function __invoke(QuestionCategory $category, array $validated): QuestionCategory
    {
        return DB::transaction(function () use ($category, $validated) {
            $category->update($validated);

            return $category->fresh();
        });
    }
}
