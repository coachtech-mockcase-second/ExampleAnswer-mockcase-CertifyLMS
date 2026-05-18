<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ChatMember.last_read_at 基準で個人別未読件数を集計する Service。
 *
 * グループ chat としての自然な「個人別既読」を実現するため、未読は全員共通ではなく
 * ログイン User 自身の last_read_at を起点に算出する。
 *
 * 一覧描画時にルーム別未読件数を attach する用途、サイドバーバッジで未読を含むルーム総数を出す用途の 2 つを担う。
 */
class ChatUnreadCountService
{
    /**
     * 指定 ChatRoom 内で User が未読のメッセージ件数を返す。
     *
     * 「未読」= sender が User 以外、かつ created_at が User の last_read_at より新しい(or last_read_at が null)。
     */
    public function messageCountInRoom(ChatRoom $room, User $user): int
    {
        $member = ChatMember::query()
            ->where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if ($member === null) {
            return 0;
        }

        return ChatMessage::query()
            ->where('chat_room_id', $room->id)
            ->where('sender_user_id', '!=', $user->id)
            ->when($member->last_read_at !== null, function ($q) use ($member): void {
                $q->where('created_at', '>', $member->last_read_at);
            })
            ->count();
    }

    /**
     * User が ChatMember として参加しているルームのうち、未読メッセージを 1 件以上含むルーム総数を返す。
     *
     * サイドバーバッジ `<x-badge>` に表示する整数 1 件分を生成する想定。0 件ならバッジ非表示。
     */
    public function roomCountForUser(User $user): int
    {
        return ChatRoom::query()
            ->whereHas('members', function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            })
            ->whereExists(function ($q) use ($user): void {
                $q->select(DB::raw(1))
                    ->from('chat_messages')
                    ->whereColumn('chat_messages.chat_room_id', 'chat_rooms.id')
                    ->where('chat_messages.sender_user_id', '!=', $user->id)
                    ->whereNull('chat_messages.deleted_at')
                    ->where(function ($inner) use ($user): void {
                        $inner->whereRaw(
                            'chat_messages.created_at > COALESCE((SELECT last_read_at FROM chat_members WHERE chat_members.chat_room_id = chat_rooms.id AND chat_members.user_id = ? AND chat_members.deleted_at IS NULL LIMIT 1), "1970-01-01")',
                            [$user->id]
                        );
                    });
            })
            ->count();
    }
}
