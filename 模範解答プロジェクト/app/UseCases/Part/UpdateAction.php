<?php

namespace App\UseCases\Part;

use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Part $part, User $actor, array $validated): Part
    {
        return DB::transaction(function () use ($part, $validated) {
            $part->update(Arr::only($validated, ['title', 'description']));

            return $part->fresh();
        });
    }
}
