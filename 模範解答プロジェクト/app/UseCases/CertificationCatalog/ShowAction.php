<?php

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
