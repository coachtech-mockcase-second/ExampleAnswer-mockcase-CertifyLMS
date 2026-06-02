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
            ->when($member->last_read_at !== null, function ($q) use ($member): void {
                $q->where('created_at', '>', $member->last_read_at);
            })
            ->count();
    }

    /**
     * 指定 User が参加する複数 ChatRoom の個人別未読件数を 1 集約クエリで返す。
     *
     * room_id をキー、未読件数(0 以上)を値とした連想配列を返す。
     * rooms-pane の各ルーム行で O(1) ルックアップしてバッジ表示する用途で、N+1 を回避する。
     *
     * @param  iterable<ChatRoom>  $rooms
     * @return array<string, int>
     */
    public function messageCountsByRoomForUser(iterable $rooms, User $user): array
    {
        $roomIds = collect($rooms)->pluck('id')->all();
        if ($roomIds === []) {
            return [];
        }

        $result = array_fill_keys($roomIds, 0);

        $members = ChatMember::query()
            ->whereIn('chat_room_id', $roomIds)
            ->where('user_id', $user->id)
            ->get(['chat_room_id', 'last_read_at']);

        if ($members->isEmpty()) {
            return $result;
        }

        $counts = ChatMessage::query()
            ->whereIn('chat_room_id', $members->pluck('chat_room_id')->all())
            ->where(function ($q) use ($members): void {
                foreach ($members as $member) {
                    $q->orWhere(function ($inner) use ($member): void {
                        $inner->where('chat_room_id', $member->chat_room_id);
                        if ($member->last_read_at !== null) {
                            $inner->where('created_at', '>', $member->last_read_at);
                        }
                    });
                }
            })
            ->groupBy('chat_room_id')
            ->selectRaw('chat_room_id, COUNT(*) as cnt')
            ->pluck('cnt', 'chat_room_id')
            ->all();

        foreach ($counts as $roomId => $cnt) {
            $result[$roomId] = (int) $cnt;
        }

        return $result;
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
                    ->where(function ($inner) use ($user): void {
                        $inner->whereRaw(
                            'chat_messages.created_at > COALESCE((SELECT last_read_at FROM chat_members WHERE chat_members.chat_room_id = chat_rooms.id AND chat_members.user_id = ? LIMIT 1), "1970-01-01")',
                            [$user->id]
                        );
                    });
            })
            ->count();
    }
}
