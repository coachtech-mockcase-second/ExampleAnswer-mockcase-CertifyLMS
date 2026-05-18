<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 受講生の予約成立を担当コーチに通知する Notification。Mail channel でコーチへ通知する。
 *
 * Mail 本文は `meeting_url_snapshot` が NULL の場合「URL 未設定」案内を入れる(NFR-mentoring 準拠)。
 * Database channel は通知一覧画面と連動するため、通知一覧 Feature 導入時に `via()` へ追加する。
 */
final class MeetingReservedNotification extends Notification
{
    public function __construct(public readonly Meeting $meeting) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing(['student', 'enrollment.certification']);
        $studentName = $meeting->student?->name ?? '受講生';
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $scheduledLabel = $meeting->scheduled_at->translatedFormat('n月j日(D) H:i');
        $url = $meeting->meeting_url_snapshot;

        $message = (new MailMessage)
            ->subject('【Certify LMS】面談予約のお知らせ')
            ->greeting('面談予約が入りました')
            ->line("受講生: {$studentName}")
            ->line("資格: {$certificationName}")
            ->line("日時: {$scheduledLabel} から 60 分")
            ->line('相談内容:')
            ->line($meeting->topic);

        if ($url !== null && $url !== '') {
            $message->action('面談 URL を開く', $url);
        } else {
            $message->line('面談 URL が未設定です。プロフィール画面から早急に設定してください。');
        }

        return $message->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_reserved',
            'meeting_id' => $this->meeting->id,
            'enrollment_id' => $this->meeting->enrollment_id,
            'student_id' => $this->meeting->student_id,
            'scheduled_at' => $this->meeting->scheduled_at->toIso8601String(),
            'topic' => $this->meeting->topic,
            'meeting_url_snapshot' => $this->meeting->meeting_url_snapshot,
            'link_route' => 'coach.meetings.index',
            'link_params' => [],
        ];
    }
}
