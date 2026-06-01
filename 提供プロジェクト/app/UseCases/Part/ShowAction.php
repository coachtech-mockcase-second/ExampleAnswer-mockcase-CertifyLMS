<?php

declare(strict_types=1);

namespace App\UseCases\Part;

use App\Models\Part;

/**
 * Part 詳細取得ユースケース。Certification と Chapter を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(Part $part): Part
    {
        return $part->load([
            'certification',
            'chapters' => fn ($q) => $q->withCount('sections'),
        ]);
    }
}
