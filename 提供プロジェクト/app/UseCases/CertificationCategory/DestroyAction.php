<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Exceptions\Certification\CertificationCategoryInUseException;
use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

/**
 * 資格分類マスタを SoftDelete するユースケース。紐付く資格があるカテゴリは削除不可。
 */
final class DestroyAction
{
    /**
     * @throws CertificationCategoryInUseException 紐付く資格が存在する
     */
    public function __invoke(CertificationCategory $category): void
    {
        if ($category->certifications()->exists()) {
            throw new CertificationCategoryInUseException;
        }

        DB::transaction(fn () => $category->delete());
    }
}
