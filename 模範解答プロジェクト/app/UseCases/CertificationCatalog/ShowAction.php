<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCatalog;

use App\Models\Certification;

class ShowAction
{
    public function __invoke(Certification $certification): Certification
    {
        return $certification->load([
            'category',
            'coaches',
        ]);
    }
}
