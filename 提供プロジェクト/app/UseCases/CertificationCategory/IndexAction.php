<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Database\Eloquent\Collection;

/**
 * 資格分類マスタの一覧を取得するユースケース。`sort_order` 昇順、紐付く資格数も同時取得する。
 */
final class IndexAction
{
    /**
     * @return Collection<int, CertificationCategory>
     */
    public function __invoke(): Collection
    {
        return CertificationCategory::query()
            ->withCount('certifications')
            ->ordered()
            ->get();
    }
}
