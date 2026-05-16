<?php

declare(strict_types=1);

namespace App\UseCases\QuestionCategory;

use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    /**
     * @param  array{name: string, slug: string, sort_order?: ?int, description?: ?string}  $validated  QuestionCategory/StoreRequest::rules() で検証済
     */
    public function __invoke(Certification $certification, User $actor, array $validated): QuestionCategory
    {
        return DB::transaction(fn () => $certification->questionCategories()->create($validated));
    }
}
