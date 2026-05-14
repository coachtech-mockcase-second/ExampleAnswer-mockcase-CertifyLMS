<?php

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(User $admin, array $validated): Certification
    {
        return DB::transaction(fn () => Certification::create([
            ...$validated,
            'status' => CertificationStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
            'published_at' => null,
            'archived_at' => null,
        ]));
    }
}
