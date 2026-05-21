<?php

declare(strict_types=1);

namespace App\UseCases\Notification\Api;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 受信者本人の通知をページネーション付きで取得する Action。
 * tab = 'unread' で未読のみ、それ以外は全件を時系列降順で返す。
 */
final class IndexAction
{
    /**
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function __invoke(User $user, string $tab = 'all', int $perPage = 20): LengthAwarePaginator
    {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
