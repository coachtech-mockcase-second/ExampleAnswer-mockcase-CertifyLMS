<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * chat メッセージ受信を ChatMember に通知する Notification。
 *
 * 配信チャネルは受信者と送信者の組み合わせで切り替える:
 *
 * - 送信者が student → コーチへ database + mail
 * - 送信者が coach、受信者が student → database + mail
 * - 送信者が coach、受信者が他コーチ → database のみ
 *
 * いずれの場合も database チャネルは共通で書き込み、Mail のみ条件分岐する。
 */
final class ChatMessageReceivedNotification extends Notification
{
    public function __construct(public readonly ChatMessage $message) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldMailTo($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message->loadMissing(['sender', 'chatRoom.enrollment.certification']);
        $senderName = $message->sender?->name ?? '送信者';
        $certificationName = $message->chatRoom?->enrollment?->certification?->name ?? '担当資格';
        $preview = mb_strimwidth($message->body, 0, 80, '…');

        $url = route('chat.show', $message->chat_room_id);

        return (new MailMessage)
            ->subject("【Certify LMS】{$senderName} さんから新着メッセージ")
            ->greeting('新着メッセージが届きました')
            ->line("資格: {$certificationName}")
            ->line("送信者: {$senderName}")
            ->line("本文プレビュー: {$preview}")
            ->action('chat を開く', $url)
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->message->loadMissing('sender');

        return [
            'chat_message_id' => $message->id,
            'chat_room_id' => $message->chat_room_id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->name,
            'body_preview' => mb_strimwidth($message->body, 0, 60, '…'),
        ];
    }

    private function shouldMailTo(object $notifiable): bool
    {
        if (! $notifiable instanceof User) {
            return false;
        }

        $sender = $this->message->loadMissing('sender')->sender;
        if ($sender === null) {
            return false;
        }

        if ($sender->role === UserRole::Student) {
            return $notifiable->role === UserRole::Coach;
        }

        return $notifiable->role === UserRole::Student;
    }
}
