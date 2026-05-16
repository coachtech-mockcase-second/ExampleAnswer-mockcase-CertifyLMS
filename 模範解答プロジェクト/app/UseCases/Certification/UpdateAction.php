<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Certification $certification, User $admin, array $validated): Certification
    {
        return DB::transaction(function () use ($certification, $admin, $validated) {
            $certification->update([
                ...$validated,
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
