<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    /**
     * Plan の削除は admin 限定。状態(draft 限定)および参照中(users.plan_id 経由)のチェックは Action 内で実施し、
     * published / archived 状態の削除要求は 409 Conflict として PlanNotDeletableException で返す。
     */
    public function delete(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
