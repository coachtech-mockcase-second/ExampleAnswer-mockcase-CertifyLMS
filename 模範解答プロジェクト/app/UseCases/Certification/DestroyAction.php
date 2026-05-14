<?php

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationNotDeletableException;
use App\Models\Certification;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Certification $certification): void
    {
        if ($certification->status !== CertificationStatus::Draft) {
            throw new CertificationNotDeletableException();
        }

        DB::transaction(fn () => $certification->delete());
    }
}
