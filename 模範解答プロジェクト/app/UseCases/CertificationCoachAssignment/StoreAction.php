<?php

namespace App\UseCases\CertificationCoachAssignment;

use App\Enums\UserRole;
use App\Exceptions\Certification\NotCoachUserException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreAction
{
    public function __invoke(Certification $certification, User $coach, User $admin): void
    {
        if ($coach->role !== UserRole::Coach) {
            throw new NotCoachUserException();
        }

        DB::transaction(function () use ($certification, $coach, $admin) {
            $certification->coaches()->syncWithoutDetaching([
                $coach->id => [
                    'id' => (string) Str::ulid(),
                    'assigned_by_user_id' => $admin->id,
                    'assigned_at' => now(),
                ],
            ]);
        });
    }
}
