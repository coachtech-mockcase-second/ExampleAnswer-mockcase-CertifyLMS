<?php

namespace App\UseCases\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Database\Eloquent\Collection;

class IndexAction
{
    public function __invoke(): Collection
    {
        return CertificationCategory::query()
            ->withCount('certifications')
            ->ordered()
            ->get();
    }
}
