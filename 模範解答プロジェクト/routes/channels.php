<?php

declare(strict_types=1);

use App\Models\ChatMember;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// chat-room の Private チャネル購読は、対象 ChatRoom の ChatMember であることを必須とする。
// admin はそもそも当事者ではないため subscribe 不可(管理画面は HTTP 越しに監査参照する)。
Broadcast::channel('chat-room.{chatRoomId}', function (User $user, string $chatRoomId): bool {
    return ChatMember::query()
        ->where('chat_room_id', $chatRoomId)
        ->where('user_id', $user->id)
        ->whereNull('deleted_at')
        ->exists();
});

// 通知のリアルタイム push 用 Private チャネル。自分宛の userId のみ subscribe 可。
Broadcast::channel('notifications.{userId}', function (User $user, string $userId): bool {
    return (string) $user->id === $userId;
});
