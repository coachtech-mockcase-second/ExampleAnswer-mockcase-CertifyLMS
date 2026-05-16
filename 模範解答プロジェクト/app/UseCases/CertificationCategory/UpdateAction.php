<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(CertificationCategory $category, array $validated): CertificationCategory
    {
        return DB::transaction(function () use ($category, $validated) {
            $category->update($validated);

            return $category->fresh();
        });
    }
}
