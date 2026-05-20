<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\AdminAnnouncement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_own_unread_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));
        $user->notify(new AdminAnnouncementNotification($announcement));
        $user->notify(new AdminAnnouncementNotification($announcement));

        $this->assertSame(3, $user->unreadNotifications()->count());

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_does_not_touch_others_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));
        $other->notify(new AdminAnnouncementNotification($announcement));

        $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }
}
