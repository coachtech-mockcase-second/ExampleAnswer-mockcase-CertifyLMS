<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Models\Certificate;

class ShowAction
{
    public function __invoke(Certificate $certificate): Certificate
    {
        return $certificate->load([
            'user',
            'certification.category',
            'enrollment',
            'issuedBy',
        ]);
    }
}
