<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Mentoring;

use App\Models\Meeting;
use App\Notifications\Mentoring\MeetingReservedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * MeetingReservedNotification の toDatabase・toMail を検証する Unit テスト。
 * 受講生の予約成立をコーチに通知する DB ペイロード (notification_type=meeting_reserved) とメール件名を網羅する。
 */
class MeetingReservedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_database_returns_meeting_reserved_payload(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create(['scheduled_at' => '2026-06-01 10:00:00']);
        $notification = new MeetingReservedNotification($meeting);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame('meeting_reserved', $payload['notification_type']);
        $this->assertSame($meeting->id, $payload['meeting_id']);
        $this->assertSame($meeting->coach_id, $payload['coach_user_id']);
        $this->assertSame('coach.meetings.index', $payload['link_route'], 'コーチ宛通知なので遷移先はコーチ面談一覧のはず');
    }

    public function test_to_mail_subject_indicates_new_reservation(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create();
        $notification = new MeetingReservedNotification($meeting);

        // Act
        $mail = $notification->toMail((object) []);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】新しい面談予約があります', $mail->subject);
    }
}
