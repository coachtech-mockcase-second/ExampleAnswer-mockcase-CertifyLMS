<?php

declare(strict_types=1);

namespace App\UseCases\Notification\Api;

use App\Models\User;

/**
 * 受信者本人の未読通知を一括既読化する Action。
 *
 * @return int 既読化した件数
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
