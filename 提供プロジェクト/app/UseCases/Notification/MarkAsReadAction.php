<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 単一の通知を既読化する Action。既読済の場合は no-op (べき等)。
 * 認可は呼出側 (Controller) で行う。
 */
final class MarkAsReadAction
{
    public function __invoke(DatabaseNotification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }

        $notification->markAsRead();
    }
}
