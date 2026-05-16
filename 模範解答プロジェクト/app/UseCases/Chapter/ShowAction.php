<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Models\Chapter;

class ShowAction
{
    public function __invoke(Chapter $chapter): Chapter
    {
        return $chapter->load([
            'part.certification',
            'sections' => fn ($q) => $q->ordered(),
        ]);
    }
}
