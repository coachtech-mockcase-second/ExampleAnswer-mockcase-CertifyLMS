<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string} $validated Part/UpdateRequest::rules() で検証済
     */
    public function __invoke(Part $part, User $actor, array $validated): Part
    {
        return DB::transaction(function () use ($part, $validated) {
            $part->update($validated);

            return $part->fresh();
        });
    }
}
