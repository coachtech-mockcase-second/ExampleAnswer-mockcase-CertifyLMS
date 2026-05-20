<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\AdminAnnouncement\AdminAnnouncementNotification;

/**
 * 管理者お知らせを単一の受講生に配信するラッパー Action。
 *
 * `Admin\AdminAnnouncement\StoreAction` から、解決済の対象 User Collection を巡回しつつ呼ばれる。
 * 受信者の `status !== InProgress` は配信スキップして dispatched_count に算入しない (呼出側で件数集計)。
 *
 * @return bool 配信したら true (呼出側で件数カウントに利用)
 */
final class NotifyAdminAnnouncementAction
{
    public function __invoke(AdminAnnouncement $announcement, User $recipient): bool
    {
        if ($recipient->status !== UserStatus::InProgress) {
            return false;
        }

        $recipient->notify(new AdminAnnouncementNotification($announcement));

        return true;
    }
}
