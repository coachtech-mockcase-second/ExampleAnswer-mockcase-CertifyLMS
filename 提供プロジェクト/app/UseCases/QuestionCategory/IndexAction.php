<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * 指定資格配下の QuestionCategory(出題分野マスタ) 一覧を、紐づく SectionQuestion 件数付きで返すユースケース。
 */
final class IndexAction
{
    /**
     * @return Collection<int, QuestionCategory>
     */
    public function __invoke(Certification $certification): Collection
    {
        return $certification->questionCategories()
            ->withCount('sectionQuestions')
            ->ordered()
            ->get();
    }
}
