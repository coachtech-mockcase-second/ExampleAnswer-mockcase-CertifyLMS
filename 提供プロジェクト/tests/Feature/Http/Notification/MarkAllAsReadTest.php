<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_own_unread_notifications_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $user->notify(new AnnouncementNotification($announcement));
        $user->notify(new AnnouncementNotification($announcement));

        $this->assertSame(3, $user->unreadNotifications()->count());

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_does_not_touch_others_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $other->notify(new AnnouncementNotification($announcement));

        $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }
}
