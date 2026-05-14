<?php

namespace App\UseCases\User;

use App\Enums\UserStatus;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(User $user, array $validated): User
    {
        if ($user->status === UserStatus::Withdrawn) {
            throw new UserAlreadyWithdrawnException();
        }

        return DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            return $user;
        });
    }
}
