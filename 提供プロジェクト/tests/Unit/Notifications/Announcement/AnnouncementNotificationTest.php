<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Announcement;

use App\Models\Announcement;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * AnnouncementNotification の toDatabase・toMail を検証する Unit テスト。
 * 管理者お知らせの DB ペイロード (notification_type / target_type / body) とメール件名の構造を網羅する。
 */
class AnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_database_returns_announcement_payload(): void
    {
        // Arrange
        $announcement = Announcement::factory()->allStudents()->create(['title' => '年末メンテナンスのお知らせ']);
        $notification = new AnnouncementNotification($announcement);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame('admin_announcement', $payload['notification_type']);
        $this->assertSame('年末メンテナンスのお知らせ', $payload['title']);
        $this->assertSame($announcement->id, $payload['admin_announcement_id']);
        $this->assertSame('notifications.show', $payload['link_route'], 'お知らせは通知詳細ページへ遷移するはず');
        $this->assertSame(['notification' => $notification->id], $payload['link_params'], '遷移先は自通知の詳細ページのはず');
    }

    public function test_to_mail_subject_includes_announcement_title(): void
    {
        // Arrange
        $announcement = Announcement::factory()->allStudents()->create(['title' => '重要なお知らせ']);
        $notification = new AnnouncementNotification($announcement);

        // Act
        $mail = $notification->toMail((object) []);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】重要なお知らせ', $mail->subject, '件名は Certify LMS プレフィクス + お知らせタイトルのはず');
    }
}
