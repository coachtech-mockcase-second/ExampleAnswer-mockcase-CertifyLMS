<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 当事者によるキャンセルを相手方に通知する Notification。
 * 送信者(actor)のロールで「受講生によるキャンセル」「コーチによるキャンセル」の文面を切り替える。
 *
 * Mail channel で相手方(受講生がキャンセルしたらコーチ、コーチがキャンセルしたら受講生)へ通知する。
 * Database channel は通知一覧 Feature 導入時に `via()` へ追加する。
 */
final class MeetingCanceledNotification extends Notification
{
    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting->loadMissing(['student', 'coach', 'enrollment.certification']);
        $scheduledLabel = $meeting->scheduled_at->translatedFormat('n月j日(D) H:i');
        $actorLabel = $this->actor->role === UserRole::Coach ? '担当コーチ' : '受講生';

        return (new MailMessage)
            ->subject('【Certify LMS】面談予約がキャンセルされました')
            ->greeting($scheduledLabel.' の面談がキャンセルされました')
            ->line('キャンセルした人: '.$actorLabel.' '.$this->actor->name)
            ->line('資格: '.($meeting->enrollment?->certification?->name ?? '担当資格'))
            ->line('相談内容: '.$meeting->topic)
            ->line('面談履歴は LMS にログインして「面談予約」からご確認いただけます。')
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_canceled',
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->actor->id,
            'actor_role' => $this->actor->role->value,
            'scheduled_at' => $this->meeting->scheduled_at->toIso8601String(),
            'link_route' => $this->actor->role === UserRole::Coach ? 'meetings.index' : 'coach.meetings.index',
            'link_params' => [],
        ];
    }
}
