<?php

namespace App\UseCases\Part;

use App\Models\Part;

class ShowAction
{
    public function __invoke(Part $part): Part
    {
        return $part->load([
            'certification',
            'chapters' => fn ($q) => $q->ordered()->withCount('sections'),
        ]);
    }
}
