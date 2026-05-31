<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * MeetingReminderNotification の toDatabase / toMail / toBroadcast 戻り値構造を直接検証する Unit テスト。
 * Eve (前日リマインダ) / OneHourBefore (1 時間前リマインダ) の 2 window で送信内容を assert する。
 * dispatch 経路は Schedule Command 側の Feature テストで検証するため、本ファイルは「ペイロード生成ロジック」に責務を絞る。
 */
class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_database_returns_expected_payload_for_eve_window(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $payload = $notification->toDatabase($meeting->student);

        // Assert
        $this->assertSame('meeting_reminder', $payload['notification_type']);
        $this->assertSame($meeting->id, $payload['meeting_id']);
        $this->assertSame($meeting->coach_id, $payload['coach_user_id']);
        $this->assertSame($meeting->student_id, $payload['student_user_id']);
        $this->assertSame('eve', $payload['window'], 'Eve window では window=eve が DB ペイロードに記録されるはず');
        $this->assertSame('meetings.show', $payload['link_route']);
        $this->assertSame(['meeting' => $meeting->id], $payload['link_params']);
    }

    public function test_to_database_records_one_hour_before_window_value(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::OneHourBefore);

        // Act
        $payload = $notification->toDatabase($meeting->student);

        // Assert
        $this->assertSame(
            'one_hour_before',
            $payload['window'],
            'OneHourBefore window では window=one_hour_before が記録され、重複検査の識別キーになるはず',
        );
    }

    public function test_to_mail_returns_mail_message_with_certify_lms_subject(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $mailMessage = $notification->toMail($meeting->student);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertStringStartsWith('【Certify LMS】', $mailMessage->subject, '件名は『【Certify LMS】』プレフィクスで始まるはず');
    }

    public function test_to_mail_includes_action_url_when_meeting_url_snapshot_present(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations(meetingUrl: 'https://meet.example.test/abc-xyz');
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $mailMessage = $notification->toMail($meeting->student);

        // Assert
        $this->assertSame(
            'https://meet.example.test/abc-xyz',
            $mailMessage->actionUrl,
            'meeting_url_snapshot が登録されている場合、action ボタンに URL が埋め込まれるはず',
        );
    }

    public function test_to_mail_omits_action_when_meeting_url_snapshot_is_null(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations(meetingUrl: null);
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $mailMessage = $notification->toMail($meeting->student);

        // Assert
        $this->assertNull(
            $mailMessage->actionUrl,
            'meeting_url_snapshot 未登録の場合、action ボタンは生成されないはず',
        );
    }

    public function test_to_broadcast_returns_message_with_to_database_payload(): void
    {
        // Arrange
        $meeting = $this->createMeetingWithRelations();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);
        $notification->id = 'test-broadcast-id';

        // Act
        $broadcast = $notification->toBroadcast($meeting->student);

        // Assert
        $this->assertInstanceOf(BroadcastMessage::class, $broadcast);
        $this->assertSame('test-broadcast-id', $broadcast->data['id']);
        $this->assertSame(MeetingReminderNotification::class, $broadcast->data['type']);
        $this->assertSame($meeting->id, $broadcast->data['data']['meeting_id']);
    }

    private function createMeetingWithRelations(?string $meetingUrl = 'https://meet.example.test/default'): Meeting
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->create();

        return Meeting::factory()->create([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'scheduled_at' => '2026-06-01 10:00:00',
            'topic' => '次回模試までの学習計画レビュー',
            'meeting_url_snapshot' => $meetingUrl,
        ]);
    }
}
