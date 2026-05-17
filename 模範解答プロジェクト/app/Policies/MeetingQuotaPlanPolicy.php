<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MeetingQuotaPlan;
use App\Models\User;

class MeetingQuotaPlanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    /**
     * 追加面談 SKU の削除は admin 限定。状態(published 不可)のチェックは Action 内で実施し、
     * 違反は MeetingQuotaPlanNotDeletableException(HTTP 409) で返す。
     */
    public function delete(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, MeetingQuotaPlan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
