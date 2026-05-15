<?php

namespace App\UseCases\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(Certification $certification, User $actor, array $validated): QuestionCategory
    {
        return DB::transaction(fn () => $certification->questionCategories()->create([
            ...Arr::only($validated, ['name', 'slug', 'sort_order', 'description']),
        ]));
    }
}
