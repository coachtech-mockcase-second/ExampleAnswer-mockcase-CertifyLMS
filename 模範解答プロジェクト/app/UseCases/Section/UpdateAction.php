<?php

namespace App\UseCases\Section;

use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Section $section, User $actor, array $validated): Section
    {
        return DB::transaction(function () use ($section, $validated) {
            $section->update(Arr::only($validated, ['title', 'description', 'body']));

            return $section->fresh();
        });
    }
}
