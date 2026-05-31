<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 通知一覧 (`/notifications`) のページネーション結果と未読件数を返す Action。
 * tab = 'unread' の場合は未読のみ、それ以外 (デフォルト 'all') は全件を時系列降順で返す。
 */
final class IndexAction
{
    /**
     * @return array{notifications: LengthAwarePaginator, tab: string, unreadCount: int}
     */
    public function __invoke(User $user, string $tab = 'all'): array
    {
        $tab = $tab === 'unread' ? 'unread' : 'all';

        $query = $user->notifications();
        if ($tab === 'unread') {
            $query = $user->unreadNotifications();
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return [
            'notifications' => $notifications,
            'tab' => $tab,
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
    }
}
