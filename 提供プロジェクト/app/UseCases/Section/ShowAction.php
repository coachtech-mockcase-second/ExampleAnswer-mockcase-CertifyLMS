<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Models\Section;

/**
 * Section 詳細取得ユースケース。親階層と画像メタデータを Eager Load する。
 */
final class ShowAction
{
    public function __invoke(Section $section): Section
    {
        return $section->load([
            'chapter.part.certification',
            'images',
        ]);
    }
}
