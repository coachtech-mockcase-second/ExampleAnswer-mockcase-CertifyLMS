<?php

declare(strict_types=1);

namespace App\UseCases\CertificationCoachAssignment;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Certification $certification, User $coach): void
    {
        DB::transaction(fn () => $certification->coaches()->detach($coach->id));
    }
}
