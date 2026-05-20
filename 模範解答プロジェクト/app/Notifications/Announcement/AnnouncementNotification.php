<?php

declare(strict_types=1);

namespace App\Notifications\Announcement;

use App\Models\Announcement;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 管理者から受講生集合への一斉お知らせを各受講生に通知する Notification。
 *
 * 配信ターゲットは `Announcement::target_type` で `AllStudents` / `Certification` / `User` を切替。
 * 通知行クリック後の遷移先は `/notifications` フルページ (お知らせ詳細は通知ペイロードに保持済)。
 */
final class AnnouncementNotification extends BaseNotification
{
    public function __construct(public readonly Announcement $announcement)
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => mb_strimwidth(strip_tags($this->announcement->body), 0, 120, '…'),
            'admin_announcement_id' => $this->announcement->id,
            'body' => $this->announcement->body,
            'dispatched_at' => $this->announcement->dispatched_at?->toIso8601String() ?? now()->toIso8601String(),
            'target_type' => $this->announcement->target_type->value,
            'link_route' => 'notifications.index',
            'link_params' => [],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】'.$this->announcement->title)
            ->greeting('管理者からのお知らせ')
            ->line($this->announcement->body)
            ->action('お知らせ一覧を開く', route('notifications.index'))
            ->salutation('Certify LMS 運営チーム');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => self::class,
            'data' => $this->toDatabase($notifiable),
            'created_at' => now()->toIso8601String(),
        ]);
    }
}
