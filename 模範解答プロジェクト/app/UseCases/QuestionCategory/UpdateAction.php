<?php

namespace App\UseCases\QuestionCategory;

use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(QuestionCategory $category, User $actor, array $validated): QuestionCategory
    {
        return DB::transaction(function () use ($category, $validated) {
            $category->update(Arr::only($validated, ['name', 'slug', 'sort_order', 'description']));

            return $category->fresh();
        });
    }
}
