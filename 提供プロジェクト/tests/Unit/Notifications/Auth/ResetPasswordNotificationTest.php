<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * ResetPasswordNotification の toMail を検証する Unit テスト。
 * Laravel 標準の ResetPassword を継承し、Certify LMS 固有の件名 + パスワード再設定 URL を生成することを網羅する。
 */
class ResetPasswordNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_mail_has_certify_lms_subject(): void
    {
        // Arrange
        $user = User::factory()->create();
        $notification = new ResetPasswordNotification('test-reset-token');

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Certify LMS パスワード再設定のご案内', $mail->subject);
    }

    public function test_to_mail_action_url_includes_token(): void
    {
        // Arrange
        $user = User::factory()->create();
        $notification = new ResetPasswordNotification('unique-token-123');

        // Act
        $mail = $notification->toMail($user);

        // Assert
        $this->assertStringContainsString('unique-token-123', $mail->actionUrl, 'パスワード再設定 URL にトークンが埋め込まれるはず');
    }
}
