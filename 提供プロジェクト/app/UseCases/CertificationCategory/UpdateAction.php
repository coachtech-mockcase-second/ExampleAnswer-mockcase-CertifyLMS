<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

/**
 * 資格分類マスタを更新するユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, slug: string, sort_order?: int|null} $validated
     */
    public function __invoke(CertificationCategory $category, array $validated): CertificationCategory
    {
        return DB::transaction(function () use ($category, $validated) {
            $category->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            ]);

            return $category->fresh();
        });
    }
}
