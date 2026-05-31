<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Models\ChatMessage;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * chat メッセージ受信を ChatMember に通知する Notification。
 *
 * Database / Mail / Broadcast の 3 チャネルを基盤で固定し、コーチ間のみ Mail を抑制する用途で
 * `mailEnabled` フラグを受け取る (受講生→コーチ、コーチ→受講生は true / コーチ→他コーチは false)。
 * Mail の抑制判断は `NotifyChatMessageReceivedAction` 側で行い、本クラスはフラグの反映のみを担当する。
 */
final class ChatMessageReceivedNotification extends BaseNotification
{
    public function __construct(
        public readonly ChatMessage $message,
        public readonly bool $mailEnabled = true,
    ) {
        parent::__construct();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->mailEnabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $message = $this->message->loadMissing('sender');

        return [
            'notification_type' => 'chat_message_received',
            'title' => ($message->sender?->name ?? '送信者').' さんから新着メッセージ',
            'message' => mb_strimwidth($message->body, 0, 100, '…'),
            'chat_room_id' => $message->chat_room_id,
            'chat_message_id' => $message->id,
            'sender_user_id' => $message->sender_user_id,
            'sender_name' => $message->sender?->name,
            'sender_role' => $message->sender?->role?->value,
            'body_preview' => mb_strimwidth($message->body, 0, 100, '…'),
            'link_route' => 'chat.show',
            'link_params' => ['room' => $message->chat_room_id],
        ];
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

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->toDatabase($notifiable);

        return new BroadcastMessage([
            'id' => $this->id,
            'type' => self::class,
            'data' => $data,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
