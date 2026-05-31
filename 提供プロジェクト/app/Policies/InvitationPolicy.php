<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

/**
 * Invitation リソースに対する認可ポリシー。
 *
 * 全 method admin のみ許可する。`revoke` は pending Invitation に限定し、accepted / expired / revoked
 * への再操作を Policy 層でブロックする(Action 内のドメイン例外と二重防御)。
 */
class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->role === UserRole::Admin
            && $invitation->status === InvitationStatus::Pending;
    }
}
