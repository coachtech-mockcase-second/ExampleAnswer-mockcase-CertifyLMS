<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\QaBoard;

use App\Models\QaReply;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * QaReplyReceivedNotification の toDatabase・toMail を検証する Unit テスト。
 * 質問掲示板の新着回答通知の DB ペイロード (notification_type / qa_thread_id / qa_reply_id) とメール件名を網羅する。
 */
class QaReplyReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_database_returns_qa_reply_payload(): void
    {
        // Arrange
        $reply = QaReply::factory()->create();
        $notification = new QaReplyReceivedNotification($reply);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame('qa_reply_received', $payload['notification_type']);
        $this->assertSame($reply->id, $payload['qa_reply_id']);
        $this->assertSame($reply->qa_thread_id, $payload['qa_thread_id']);
        $this->assertSame('qa-board.show', $payload['link_route']);
    }

    public function test_to_mail_subject_includes_replier_name(): void
    {
        // Arrange
        $reply = QaReply::factory()->create();
        $notification = new QaReplyReceivedNotification($reply);

        // Act
        $mail = $notification->toMail((object) []);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringStartsWith('【Certify LMS】', $mail->subject);
        $this->assertStringContainsString('新着回答', $mail->subject);
    }
}
