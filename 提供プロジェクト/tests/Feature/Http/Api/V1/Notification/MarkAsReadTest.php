<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();

        $response = $this->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertUnauthorized();
    }

    public function test_marks_own_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_is_idempotent_when_already_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();
        $notification->markAsRead();

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertOk();
    }

    public function test_forbids_marking_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $owner->notify(new AnnouncementNotification($announcement));
        $notification = $owner->unreadNotifications->first();

        $response = $this->actingAs($intruder)->postJson(route('api.v1.notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_returns_404_for_nonexistent_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', 'nonexistent-id'));

        $response->assertNotFound();
    }
}
