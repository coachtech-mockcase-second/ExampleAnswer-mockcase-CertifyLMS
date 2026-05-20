<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_own_notification_as_read_and_redirects_to_link_route(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();

        $response = $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_others_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $other->notify(new AnnouncementNotification($announcement));
        $notification = $other->unreadNotifications->first();

        $response = $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_idempotent_for_already_read_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();
        $notification->markAsRead();
        $firstReadAt = $notification->fresh()->read_at;

        $response = $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect();
        $this->assertEquals($firstReadAt->toIso8601String(), $notification->fresh()->read_at->toIso8601String());
    }
}
