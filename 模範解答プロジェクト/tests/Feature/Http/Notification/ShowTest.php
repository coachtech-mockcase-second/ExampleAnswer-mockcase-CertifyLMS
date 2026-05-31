<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 通知詳細ページ (`NotificationController::show`) の検証。
 * 自分宛通知の本文全文表示 / 他人宛 403 / 未認証リダイレクト / 開封時の既読化を網羅する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_notification_with_full_body(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create([
            'body' => 'メンテナンスは 6 月 1 日 0:00〜3:00 に実施します。ご利用いただけません。',
        ]);
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->notifications()->first();

        // Act
        $response = $this->actingAs($user)->get(route('notifications.show', $notification));

        // Assert
        $response->assertOk();
        $response->assertSee('メンテナンスは 6 月 1 日 0:00〜3:00 に実施します。ご利用いただけません。');
    }

    public function test_cannot_view_others_notification(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $other->notify(new AnnouncementNotification($announcement));
        $notification = $other->notifications()->first();

        // Act
        $response = $this->actingAs($user)->get(route('notifications.show', $notification));

        // Assert
        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->notifications()->first();

        // Act
        $response = $this->get(route('notifications.show', $notification));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_opening_marks_notification_as_read(): void
    {
        // Arrange
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at, '前提: 配信直後の通知は未読のはず');

        // Act
        $this->actingAs($user)->get(route('notifications.show', $notification));

        // Assert
        $this->assertNotNull($notification->fresh()->read_at, '詳細ページを開いた時点で既読化されるはず');
    }
}
