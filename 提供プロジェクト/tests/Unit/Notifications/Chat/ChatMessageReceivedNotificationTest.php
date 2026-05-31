<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Chat;

use App\Models\ChatMessage;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * ChatMessageReceivedNotification の via 切替・toDatabase・toMail を検証する Unit テスト。
 * mailEnabled フラグによる mail チャネルの抑制 (コーチ間 DB only) と、DB ペイロード / メール件名の構造を網羅する。
 */
class ChatMessageReceivedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_includes_mail_when_mail_enabled(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $notification = new ChatMessageReceivedNotification($message, mailEnabled: true);

        // Act
        $channels = $notification->via((object) []);

        // Assert
        $this->assertContains('mail', $channels, 'mailEnabled=true では mail チャネルが含まれるはず');
        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
    }

    public function test_via_excludes_mail_when_mail_disabled(): void
    {
        // Arrange: コーチ間メッセージは mail を抑制する
        $message = ChatMessage::factory()->fromCoach()->create();
        $notification = new ChatMessageReceivedNotification($message, mailEnabled: false);

        // Act
        $channels = $notification->via((object) []);

        // Assert
        $this->assertNotContains('mail', $channels, 'mailEnabled=false では mail チャネルが除外されるはず');
        $this->assertContains('database', $channels);
    }

    public function test_to_database_returns_chat_message_payload(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $notification = new ChatMessageReceivedNotification($message);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame('chat_message_received', $payload['notification_type']);
        $this->assertSame($message->id, $payload['chat_message_id']);
        $this->assertSame($message->chat_room_id, $payload['chat_room_id']);
        $this->assertSame('chat.show', $payload['link_route']);
    }

    public function test_to_mail_subject_includes_sender_name(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $notification = new ChatMessageReceivedNotification($message);

        // Act
        $mail = $notification->toMail((object) []);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringStartsWith('【Certify LMS】', $mail->subject, '件名は Certify LMS プレフィクスで始まるはず');
        $this->assertStringContainsString('新着メッセージ', $mail->subject);
    }
}
