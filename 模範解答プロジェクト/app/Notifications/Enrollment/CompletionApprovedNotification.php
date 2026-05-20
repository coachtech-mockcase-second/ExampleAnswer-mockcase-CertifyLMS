<?php

declare(strict_types=1);

namespace App\Notifications\Enrollment;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 修了証発行完了を受講生本人に通知する Notification。
 *
 * 発火元は受講生が「修了証を受け取る」操作を行った時点 (admin 承認フローではなく自己発火)。
 * Mail 本文には修了証 PDF のダウンロード URL を含める。
 */
final class CompletionApprovedNotification extends BaseNotification
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Certificate $certificate,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $enrollment = $this->enrollment->loadMissing('certification');
        $certificationName = $enrollment->certification?->name ?? '取得資格';

        return [
            'notification_type' => 'completion_approved',
            'title' => "【修了】{$certificationName} の修了証が発行されました",
            'message' => 'おめでとうございます。修了証をダウンロードできます。',
            'enrollment_id' => $enrollment->id,
            'certification_id' => $enrollment->certification_id,
            'certification_name' => $certificationName,
            'certificate_id' => $this->certificate->id,
            'certificate_serial_no' => $this->certificate->serial_no,
            'passed_at' => $enrollment->passed_at?->toIso8601String(),
            'link_route' => 'certificates.download',
            'link_params' => ['certificate' => $this->certificate->id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $enrollment = $this->enrollment->loadMissing('certification');
        $certificationName = $enrollment->certification?->name ?? '取得資格';
        $downloadUrl = route('certificates.download', $this->certificate);

        return (new MailMessage)
            ->subject('【Certify LMS】修了証が発行されました')
            ->greeting('修了おめでとうございます')
            ->line("資格: {$certificationName}")
            ->line("修了証番号: {$this->certificate->serial_no}")
            ->line('以下の URL から修了証 PDF をダウンロードいただけます。')
            ->action('修了証をダウンロード', $downloadUrl)
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
