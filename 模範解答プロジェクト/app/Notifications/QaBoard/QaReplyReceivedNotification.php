<?php

declare(strict_types=1);

namespace App\Notifications\QaBoard;

use App\Models\QaReply;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 質問掲示板スレッドへの新規回答をスレッド投稿者(受講生)に通知する Notification。
 * Database / Mail / Broadcast の 3 チャネル固定送信。自己回答スキップは Action 側で処理する。
 */
final class QaReplyReceivedNotification extends BaseNotification
{
    public function __construct(public readonly QaReply $reply)
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $reply = $this->reply->loadMissing(['user', 'thread']);

        return [
            'notification_type' => 'qa_reply_received',
            'title' => ($reply->user?->name ?? '回答者').' さんからの新着回答',
            'message' => mb_strimwidth($reply->body, 0, 100, '…'),
            'qa_thread_id' => $reply->qa_thread_id,
            'qa_reply_id' => $reply->id,
            'replier_user_id' => $reply->user_id,
            'replier_name' => $reply->user?->name,
            'thread_title' => $reply->thread?->title,
            'body_preview' => mb_strimwidth($reply->body, 0, 60, '…'),
            'link_route' => 'qa-board.show',
            'link_params' => ['thread' => $reply->qa_thread_id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reply = $this->reply->loadMissing(['user', 'thread.certification']);
        $replierName = $reply->user?->name ?? '回答者';
        $threadTitle = $reply->thread?->title ?? '質問スレッド';
        $certificationName = $reply->thread?->certification?->name ?? '担当資格';
        $preview = mb_strimwidth($reply->body, 0, 80, '…');
        $url = route('qa-board.show', $reply->qa_thread_id).'#reply-'.$reply->id;

        return (new MailMessage)
            ->subject("【Certify LMS】{$replierName} さんからの新着回答")
            ->greeting('質問掲示板に新しい回答が届きました')
            ->line("資格: {$certificationName}")
            ->line("スレッド: {$threadTitle}")
            ->line("回答者: {$replierName}")
            ->line("回答プレビュー: {$preview}")
            ->action('スレッドを開く', $url)
            ->salutation('Certify LMS 運営チーム');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => static::class,
            'data' => $this->toDatabase($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
