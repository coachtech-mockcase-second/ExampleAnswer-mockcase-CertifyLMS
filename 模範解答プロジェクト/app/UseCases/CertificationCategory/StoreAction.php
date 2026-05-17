<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

/**
 * 資格分類マスタを新規作成するユースケース。
 */
final class StoreAction
{
    /**
     * @param  array{name: string, slug: string, sort_order?: int|null}  $validated
     */
    public function __invoke(array $validated): CertificationCategory
    {
        return DB::transaction(fn () => CertificationCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));
    }
}
