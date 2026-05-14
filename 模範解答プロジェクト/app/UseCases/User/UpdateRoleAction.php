<?php

namespace App\UseCases\User;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\UserManagement\SelfRoleChangeForbiddenException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateRoleAction
{
    public function __invoke(User $user, UserRole $newRole, User $admin): User
    {
        if ($user->is($admin)) {
            throw new SelfRoleChangeForbiddenException();
        }

        if ($user->status === UserStatus::Withdrawn) {
            throw new UserAlreadyWithdrawnException();
        }

        return DB::transaction(function () use ($user, $newRole) {
            $user->update(['role' => $newRole->value]);

            return $user;
        });
    }
}
