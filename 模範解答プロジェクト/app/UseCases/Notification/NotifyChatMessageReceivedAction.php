<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ChatMessage;
use App\Notifications\Chat\ChatMessageReceivedNotification;

/**
 * chat メッセージの受信通知を当事者に配信するラッパー Action。
 *
 * StoreMessageAction の `DB::afterCommit()` 内で呼ばれ、送信者を除く全 ChatMember に対して
 * Notification を配信する。受信者ロール × 送信者ロールでチャネル分岐:
 *
 * - 受講生 → コーチ: database + mail (全員)
 * - コーチ → 受講生: database + mail
 * - コーチ → 他コーチ: database のみ (連絡過剰防止のため Mail 抑制)
 *
 * 受信者の `status !== InProgress` (withdrawn / graduated / invited) は配信スキップする。
 */
final class NotifyChatMessageReceivedAction
{
    public function __invoke(ChatMessage $message): void
    {
        $message->loadMissing(['sender', 'chatRoom.members.user']);

        $sender = $message->sender;
        if ($sender === null) {
            return;
        }

        $members = $message->chatRoom?->members ?? collect();

        foreach ($members as $member) {
            $recipient = $member->user;

            if ($recipient === null) {
                continue;
            }
            if ($recipient->id === $sender->id) {
                continue;
            }
            if ($recipient->status !== UserStatus::InProgress) {
                continue;
            }

            $mailEnabled = match (true) {
                $sender->role === UserRole::Student => true,
                $sender->role === UserRole::Coach && $recipient->role === UserRole::Student => true,
                $sender->role === UserRole::Coach && $recipient->role === UserRole::Coach => false,
                default => true,
            };

            $recipient->notify(new ChatMessageReceivedNotification($message, mailEnabled: $mailEnabled));
        }
    }
}
