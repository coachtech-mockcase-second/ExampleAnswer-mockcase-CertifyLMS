<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * TopBar 通知ベルクリック時に開く通知ポップオーバーの最新 20 件 + 未読件数を返す Action。
 * tab = 'unread' で未読のみ、'all' (デフォルト) で全件を時系列降順で返す。
 */
final class PopoverAction
{
    private const POPOVER_LIMIT = 20;

    /**
     * @return array{items: array<int, array<string, mixed>>, unread_count: int, tab: string}
     */
    public function __invoke(User $user, string $tab = 'all'): array
    {
        $tab = $tab === 'unread' ? 'unread' : 'all';

        $query = $user->notifications();
        if ($tab === 'unread') {
            $query = $user->unreadNotifications();
        }

        /** @var Collection<int, DatabaseNotification> $items */
        $items = $query
            ->orderByDesc('created_at')
            ->limit(self::POPOVER_LIMIT)
            ->get();

        return [
            'items' => $items->map(fn ($n) => $this->serialize($n))->all(),
            'unread_count' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'notification_type' => $data['notification_type'] ?? null,
            'title' => $data['title'] ?? '通知',
            'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
            'link_route' => $data['link_route'] ?? null,
            'link_params' => $data['link_params'] ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_relative' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
