<?php

declare(strict_types=1);

namespace App\UseCases\SectionQuestion;

use App\Models\SectionQuestion;

/**
 * SectionQuestion 詳細取得ユースケース。Section / Chapter / Part / Certification と category / options を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(SectionQuestion $question): SectionQuestion
    {
        return $question->load([
            'section.chapter.part.certification',
            'category',
            'options' => fn ($q) => $q->ordered(),
        ]);
    }
}
