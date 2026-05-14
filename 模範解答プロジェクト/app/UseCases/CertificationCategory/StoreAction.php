<?php

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(array $validated): CertificationCategory
    {
        return DB::transaction(fn () => CertificationCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));
    }
}
