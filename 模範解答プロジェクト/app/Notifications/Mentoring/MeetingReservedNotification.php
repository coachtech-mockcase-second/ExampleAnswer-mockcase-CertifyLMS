<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Models\Meeting;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 受講生の予約成立を担当コーチに通知する Notification。
 *
 * 受講生向けの予約完了通知は予約 UI 側で即時表示されるため発火しない (コーチ宛のみ)。
 * Mail 本文には日時 / 受講生名 / 相談内容 / 面談 URL を含める。
 */
final class MeetingReservedNotification extends BaseNotification
{
    public function __construct(public readonly Meeting $meeting)
    {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $meeting = $this->meeting->loadMissing(['student', 'enrollment.certification']);
        $studentName = $meeting->student?->name ?? '受講生';
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';

        return [
            'notification_type' => 'meeting_reserved',
            'title' => "{$studentName} さんから面談予約が入りました",
            'message' => $meeting->scheduled_at->translatedFormat('n月j日(D) H:i').'〜 / '.$certificationName,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'student_name' => $studentName,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'meeting_url_snapshot' => $meeting->meeting_url_snapshot,
            'link_route' => 'coach.meetings.index',
            'link_params' => [],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing(['student', 'enrollment.certification']);
        $studentName = $meeting->student?->name ?? '受講生';
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $scheduledLabel = $meeting->scheduled_at->translatedFormat('n月j日(D) H:i');
        $url = $meeting->meeting_url_snapshot;

        $message = (new MailMessage)
            ->subject('【Certify LMS】新しい面談予約があります')
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
