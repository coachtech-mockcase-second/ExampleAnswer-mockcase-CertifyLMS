<?php

declare(strict_types=1);

namespace App\UseCases\Section;

use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @param  array{title: string, description?: ?string, body: string}  $validated  Section/UpdateRequest::rules() で検証済
 */
class UpdateAction
{
    public function __invoke(Section $section, User $actor, array $validated): Section
    {
        return DB::transaction(function () use ($section, $validated) {
            $section->update($validated);

            return $section->fresh();
        });
    }
}
