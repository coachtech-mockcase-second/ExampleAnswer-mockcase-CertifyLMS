<?php

declare(strict_types=1);

namespace App\Notifications\Mentoring;

use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 当事者によるキャンセルを相手方に通知する Notification。
 *
 * 送信者 (actor) のロールで文面を切り替える: 受講生キャンセル → コーチ宛 / コーチキャンセル → 受講生宛。
 * 受信者の遷移リンクは role に合わせて切り替える (`meetings.index` か `coach.meetings.index`)。
 */
final class MeetingCanceledNotification extends BaseNotification
{
    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $meeting = $this->meeting->loadMissing(['enrollment.certification']);
        $certificationName = $meeting->enrollment?->certification?->name ?? '担当資格';
        $actorLabel = $this->actor->role === UserRole::Coach ? 'コーチ' : '受講生';

        return [
            'notification_type' => 'meeting_canceled',
            'title' => "{$actorLabel} {$this->actor->name} さんが面談をキャンセルしました",
            'message' => $meeting->scheduled_at->translatedFormat('n月j日(D) H:i').'〜 / '.$certificationName,
            'meeting_id' => $meeting->id,
            'enrollment_id' => $meeting->enrollment_id,
            'coach_user_id' => $meeting->coach_id,
            'student_user_id' => $meeting->student_id,
            'actor_user_id' => $this->actor->id,
            'actor_role' => $this->actor->role->value,
            'scheduled_at' => $meeting->scheduled_at->toIso8601String(),
            'topic' => $meeting->topic,
            'link_route' => $this->actor->role === UserRole::Coach ? 'meetings.index' : 'coach.meetings.index',
            'link_params' => [],
        ];
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
