<?php

declare(strict_types=1);

namespace App\UseCases\Chapter;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string} $validated Chapter/UpdateRequest::rules() で検証済
     */
    public function __invoke(Chapter $chapter, User $actor, array $validated): Chapter
    {
        return DB::transaction(function () use ($chapter, $validated) {
            $chapter->update($validated);

            return $chapter->fresh();
        });
    }
}
