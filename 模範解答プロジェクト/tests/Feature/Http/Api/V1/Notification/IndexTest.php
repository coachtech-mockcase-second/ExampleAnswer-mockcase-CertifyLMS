<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api\V1\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson(route('api.v1.notifications.index'));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_sees_only_own_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $other->notify(new AnnouncementNotification($announcement));

        $response = $this->actingAs($user)->getJson(route('api.v1.notifications.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'title', 'message', 'read_at', 'created_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_unread_tab_filters_to_unread_only(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $user->unreadNotifications->first()?->markAsRead();
        $user->notify(new AnnouncementNotification($announcement));

        $response = $this->actingAs($user)->getJson(route('api.v1.notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_validation_rejects_invalid_tab(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->getJson(route('api.v1.notifications.index', ['tab' => 'invalid']));

        $response->assertStatus(422);
    }
}
