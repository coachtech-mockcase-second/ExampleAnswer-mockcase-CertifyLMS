<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * ChatRoom リソースに対する認可ポリシー。
 *
 * - viewAny: admin / coach / student いずれも一覧自体は閲覧可(取得スコープは Action 側で絞る)
 * - view: admin は全件、coach / student は ChatMember として参加しているルームのみ
 * - sendMessage: admin は送信不可、coach / student は view 条件 + 担当コーチが資格に 1 人以上割当てられていること
 *
 * 担当コーチ未割当時は送信元(受講生 / コーチ)に応じて Controller 側で
 * `CertificationCoachNotAssignedForChatException` (422) を分岐 throw する想定。
 */
class ChatRoomPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, ChatRoom $room): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return ChatMember::query()
            ->where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function sendMessage(User $user, ChatRoom $room): bool
    {
        if ($user->role === UserRole::Admin) {
            return false;
        }

        if (! $this->view($user, $room)) {
            return false;
        }

        $room->loadMissing('enrollment.certification.coaches');

        return $room->enrollment->certification->coaches->isNotEmpty();
    }
}
