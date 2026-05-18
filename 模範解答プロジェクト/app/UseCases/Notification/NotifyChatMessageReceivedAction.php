<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\ChatMessage;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Support\Facades\Notification;

/**
 * chat メッセージの受信通知を当事者に配信するラッパー Action。
 *
 * StoreMessageAction が `DB::afterCommit()` 内で呼び、送信者を除く全 ChatMember に対し
 * Notification を一括 send する。チャネル分岐は ChatMessageReceivedNotification::via() に集約。
 *
 * 送信スキップ条件:
 *
 * - 送信者本人(自分宛通知は不要)
 * - withdrawn ユーザー(退会済への通知抑止)
 *
 * @see StoreMessageAction
 */
final class NotifyChatMessageReceivedAction
{
    public function __invoke(ChatMessage $message): void
    {
        $message->loadMissing('chatRoom.members.user');

        $recipients = $message->chatRoom?->members
            ->pluck('user')
            ->filter(fn ($user) => $user !== null
                && $user->id !== $message->sender_user_id
                && $user->status !== UserStatus::Withdrawn)
            ->values();

        if ($recipients === null || $recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ChatMessageReceivedNotification($message));
    }
}
