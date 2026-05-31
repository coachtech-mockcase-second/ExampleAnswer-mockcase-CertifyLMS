<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知 (DatabaseNotification) リソースに対する認可ポリシー。
 *
 * 通知は自分宛のみ閲覧 / 既読化が可能。`notifiable_id === $user->id` を判定し、他人の通知への操作は不許可。
 * ロール (admin / coach / student) による分岐はせず、全ロール共通で「自分宛のみ」とする。
 */
class NotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_id === $user->id;
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_id === $user->id;
    }
}
