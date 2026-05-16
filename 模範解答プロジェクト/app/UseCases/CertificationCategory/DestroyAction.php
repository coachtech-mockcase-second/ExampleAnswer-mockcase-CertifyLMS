<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCategory;

use App\Exceptions\Certification\CertificationCategoryInUseException;
use App\Models\CertificationCategory;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(CertificationCategory $category): void
    {
        if ($category->certifications()->exists()) {
            throw new CertificationCategoryInUseException;
        }

        DB::transaction(fn () => $category->delete());
    }
}
