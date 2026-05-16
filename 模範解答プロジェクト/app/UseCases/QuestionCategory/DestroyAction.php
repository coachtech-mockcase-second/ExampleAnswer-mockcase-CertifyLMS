<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Models\QuestionCategory;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(QuestionCategory $category): void
    {
        if ($category->questions()->exists()) {
            throw new QuestionCategoryInUseException;
        }

        DB::transaction(fn () => $category->delete());
    }
}
