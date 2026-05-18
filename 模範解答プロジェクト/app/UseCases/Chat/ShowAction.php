<?php

declare(strict_types=1);

namespace App\UseCases\Chat;

use App\Enums\UserRole;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * ChatRoom 詳細を取得し、閲覧者の `ChatMember.last_read_at` を更新する Action。
 *
 * - 当事者(coach / student): viewer 自身の last_read_at = now() に UPDATE
 * - admin: last_read_at を更新しない(監査閲覧のため当事者の既読状態に影響させない)
 *
 * 個人別既読を実現するため、他 ChatMember の last_read_at には一切触らない。
 */
final class ShowAction
{
    public function __invoke(ChatRoom $room, User $viewer): ChatRoom
    {
        $room->load([
            'enrollment.certification.coaches',
            'enrollment.user',
            'members.user',
            'messages' => fn ($q) => $q->orderBy('created_at')->with('sender'),
        ]);

        if ($viewer->role !== UserRole::Admin) {
            ChatMember::query()
                ->where('chat_room_id', $room->id)
                ->where('user_id', $viewer->id)
                ->update(['last_read_at' => now()]);
        }

        return $room;
    }
}
