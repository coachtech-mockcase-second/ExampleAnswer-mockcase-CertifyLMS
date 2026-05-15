<?php

namespace App\UseCases\Chapter;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Chapter $chapter, User $actor, array $validated): Chapter
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $chapter->update(Arr::only($validated, ['title', 'description']));

            return $chapter->fresh();
        });
    }
}
