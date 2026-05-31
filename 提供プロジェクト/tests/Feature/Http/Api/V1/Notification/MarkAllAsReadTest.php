<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson(route('api.v1.notifications.markAllAsRead'));

        $response->assertUnauthorized();
    }

    public function test_marks_only_own_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $user->notify(new AnnouncementNotification($announcement));
        $other->notify(new AnnouncementNotification($announcement));

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAllAsRead'));

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'updated' => 2]);
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_returns_zero_when_no_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.notifications.markAllAsRead'));

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'updated' => 0]);
    }
}
