<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Enrollment\CompletionApprovedNotification;
use App\UseCases\Notification\NotifyCompletionApprovedAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * v3 で発火元が `ReceiveCertificateAction` に変更された自己発火型通知の検証。
 */
class NotifyCompletionApprovedActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_notification_to_student_with_pdf_download_url(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $certificate = Certificate::factory()->for($student)->for($certification)->for($enrollment)->create([
            'serial_no' => 'CRT-2026-00001',
        ]);

        app(NotifyCompletionApprovedAction::class)($enrollment, $certificate);

        Notification::assertSentTo($student, CompletionApprovedNotification::class, function ($notif) use ($certificate, $student) {
            /** @var MailMessage $mail */
            $mail = $notif->toMail($student);

            return collect($mail->introLines)->contains(fn ($line) => str_contains($line, $certificate->serial_no));
        });
    }

    public function test_skips_notification_if_student_is_withdrawn(): void
    {
        Notification::fake();
        $student = User::factory()->student()->withdrawn()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $certificate = Certificate::factory()->for($student)->for($certification)->for($enrollment)->create();

        app(NotifyCompletionApprovedAction::class)($enrollment, $certificate);

        Notification::assertNothingSent();
    }
}
