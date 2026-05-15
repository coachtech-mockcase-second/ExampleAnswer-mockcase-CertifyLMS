<?php

namespace App\UseCases\Section;

use App\Models\Section;

class ShowAction
{
    public function __invoke(Section $section): Section
    {
        return $section->load([
            'chapter.part.certification',
            'images',
        ]);
    }
}
