<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Collection;

class IndexAction
{
    public function __invoke(Certification $certification): Collection
    {
        return $certification->parts()
            ->with(['chapters' => fn ($q) => $q->ordered()->withCount('sections')])
            ->ordered()
            ->get();
    }
}
