<?php

declare(strict_types=1);

namespace App\UseCases\AdminChatRoom;

use App\Models\ChatRoom;

/**
 * 管理者向け chat 詳細 Action。当事者の last_read_at は更新せず、監査ビューとして閲覧する。
 */
final class ShowAction
{
    public function __invoke(ChatRoom $room): ChatRoom
    {
        $room->load([
            'enrollment.certification.coaches',
            'enrollment.user',
            'members.user',
            'messages' => fn ($q) => $q->orderBy('created_at')->with('sender'),
        ]);

        return $room;
    }
}
