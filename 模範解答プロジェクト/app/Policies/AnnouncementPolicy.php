<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

/**
 * 管理者お知らせ (Announcement) リソースに対する認可ポリシー。
 *
 * 配信 UI は admin 限定。配信履歴の閲覧も admin 限定 (受講生は通知一覧側で受信履歴を見る)。
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
