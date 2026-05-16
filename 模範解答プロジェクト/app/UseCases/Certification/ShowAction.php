<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Models\Certification;

class ShowAction
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
