<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 予約済の面談を当事者 (受講生 + コーチ両方) にリマインドする Notification。
 *
 * 発火は `notifications:send-meeting-reminders` Schedule Command 経由。
 * window で前日 (Eve) / 1 時間前 (OneHourBefore) を区別し、`data.window` に値を埋めて
 * 重複検査時の JSON path クエリで参照する (同一 meeting × window で再 dispatch をスキップ)。
 */
final class MeetingReminderNotification extends BaseNotification
{
    public function __construct(
        public readonly Meeting $meeting,
        public readonly MeetingReminderWindow $window,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $meeting = $this->meeting->loadMissing(['student', 'coach', 'enrollment.certification']);
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';

        return [
            'notification_type' => 'meeting_reminder',
            'title' => $this->window->label().': '.$meeting->scheduled_at->translatedFormat('n月j日(D) H:i'),
            'message' => $certificationName.' / '.$meeting->topic,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'window' => $this->window->value,
            'link_route' => 'meetings.show',
            'link_params' => ['meeting' => $meeting->id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing(['student', 'coach', 'enrollment.certification']);
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $scheduledLabel = $meeting->scheduled_at->translatedFormat('n月j日(D) H:i');
        $url = $meeting->meeting_url_snapshot;

        $message = (new MailMessage)
            ->subject('【Certify LMS】'.$this->window->label())
            ->greeting($this->window->label().' のお知らせ')
            ->line("日時: {$scheduledLabel} から 60 分")
            ->line("資格: {$certificationName}")
            ->line('相談内容:')
            ->line($meeting->topic);

        if ($url !== null && $url !== '') {
            $message->action('面談 URL を開く', $url);
        }

        return $message->salutation('Certify LMS 運営チーム');
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
