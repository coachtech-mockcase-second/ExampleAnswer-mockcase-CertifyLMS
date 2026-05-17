<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCatalog;

use App\Models\Certification;

/**
 * 受講生向け資格詳細を取得するユースケース。
 */
final class ShowAction
{
    public function __invoke(Certification $certification): Certification
    {
        return $certification->load([
            'category',
            'coaches',
        ]);
    }
}
