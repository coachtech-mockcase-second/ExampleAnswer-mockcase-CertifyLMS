<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Mentoring;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * MeetingCanceledNotification の toDatabase・toMail を検証する Unit テスト。
 * キャンセル実行者 (actor) のロールに応じた link_route 切替 (受講生キャンセル → コーチ面談一覧 / コーチキャンセル → 受講生面談一覧) を網羅する。
 */
class MeetingCanceledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_database_includes_actor_information(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->forCoach($coach)->forStudent($student)->canceled()->create();
        $notification = new MeetingCanceledNotification($meeting, $student);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame('meeting_canceled', $payload['notification_type']);
        $this->assertSame($student->id, $payload['actor_user_id']);
        $this->assertSame($student->role->value, $payload['actor_role']);
    }

    public function test_link_route_targets_coach_index_when_student_cancels(): void
    {
        // Arrange: 受講生がキャンセル → コーチ宛通知なので coach.meetings.index へ
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->forStudent($student)->canceled()->create();
        $notification = new MeetingCanceledNotification($meeting, $student);

        // Act
        $payload = $notification->toDatabase((object) []);

        // Assert
        $this->assertSame(
            'coach.meetings.index',
            $payload['link_route'],
            '受講生キャンセル時の通知はコーチの面談一覧に遷移するはず',
        );
    }

    public function test_to_mail_subject_indicates_cancellation(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->forCoach($coach)->canceled()->create();
        $notification = new MeetingCanceledNotification($meeting, $coach);

        // Act
        $mail = $notification->toMail((object) []);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('【Certify LMS】面談予約がキャンセルされました', $mail->subject);
    }
}
