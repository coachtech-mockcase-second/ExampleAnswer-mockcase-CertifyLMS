<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\AdminAnnouncement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_latest_20_items_with_unread_count_for_self(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();

        // 25 件流す、20 件しか返らない
        for ($i = 0; $i < 25; $i++) {
            $user->notify(new AdminAnnouncementNotification($announcement));
        }

        $response = $this->actingAs($user)->getJson(route('notifications.popover'));

        $response->assertOk();
        $response->assertJsonCount(20, 'items');
        $this->assertSame(25, $response->json('unread_count'));
        $this->assertSame('all', $response->json('tab'));
    }

    public function test_unread_tab_filters_to_unread_only(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));
        $user->unreadNotifications->first()?->markAsRead();
        $user->notify(new AdminAnnouncementNotification($announcement));

        $response = $this->actingAs($user)->getJson(route('notifications.popover', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertJsonCount(1, 'items');
        $this->assertSame(1, $response->json('unread_count'));
        $this->assertSame('unread', $response->json('tab'));
    }

    public function test_unauthenticated_request_redirects_or_401(): void
    {
        $response = $this->getJson(route('notifications.popover'));

        $this->assertContains($response->status(), [401, 302]);
    }
}
