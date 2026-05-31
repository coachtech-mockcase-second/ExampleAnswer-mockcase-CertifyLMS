<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Models\Chapter;

/**
 * Chapter 詳細取得ユースケース。親 Part / Certification と配下 Section を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(Chapter $chapter): Chapter
    {
        return $chapter->load([
            'part.certification',
            'sections' => fn ($q) => $q->ordered(),
        ]);
    }
}
