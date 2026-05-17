<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Models\Certification;

/**
 * admin 用の資格マスタ詳細を取得するユースケース。
 * 担当コーチ / 直近 10 件の発行済修了証 / 受講登録数を Eager Loading で揃える。
 */
final class ShowAction
{
    public function __invoke(Certification $certification): Certification
    {
        return $certification
            ->load([
                'category',
                'coaches',
                'createdBy',
                'updatedBy',
                'certificates' => fn ($q) => $q->latest('issued_at')->limit(10),
                'certificates.user',
            ])
            ->loadCount(['certificates', 'enrollments']);
    }
}
