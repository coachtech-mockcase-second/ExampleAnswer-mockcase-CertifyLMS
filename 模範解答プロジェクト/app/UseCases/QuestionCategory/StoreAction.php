<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

/**
 * QuestionCategory(出題分野マスタ) の新規作成ユースケース。指定資格配下に紐付けて INSERT する。
 */
final class StoreAction
{
    /**
     * @param array{name: string, slug: string, sort_order?: ?int, description?: ?string} $validated QuestionCategory/StoreRequest::rules() で検証済
     */
    public function __invoke(Certification $certification, array $validated): QuestionCategory
    {
        return DB::transaction(fn () => $certification->questionCategories()->create($validated));
    }
}
