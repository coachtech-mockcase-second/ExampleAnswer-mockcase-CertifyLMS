<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 自分宛の未読通知を一括既読化する Action。Laravel 標準の `unreadNotifications` リレーション経由で UPDATE。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
