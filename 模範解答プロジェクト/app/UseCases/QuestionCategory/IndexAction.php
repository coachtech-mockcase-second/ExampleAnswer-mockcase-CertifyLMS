<?php

namespace App\UseCases\QuestionCategory;

use App\Models\Certification;
use Illuminate\Database\Eloquent\Collection;

class IndexAction
{
    public function __invoke(Certification $certification): Collection
    {
        return $certification->questionCategories()
            ->withCount('questions')
            ->ordered()
            ->get();
    }
}
