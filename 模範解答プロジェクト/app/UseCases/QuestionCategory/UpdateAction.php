<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    /**
     * @param  array{name: string, slug: string, sort_order?: ?int, description?: ?string}  $validated  QuestionCategory/UpdateRequest::rules() で検証済
     */
    public function __invoke(QuestionCategory $category, User $actor, array $validated): QuestionCategory
    {
        return DB::transaction(function () use ($category, $validated) {
            $category->update($validated);

            return $category->fresh();
        });
    }
}
