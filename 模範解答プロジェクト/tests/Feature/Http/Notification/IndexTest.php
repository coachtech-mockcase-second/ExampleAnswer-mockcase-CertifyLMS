<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use App\Notifications\AdminAnnouncement\AdminAnnouncementNotification;
use App\Models\AdminAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get(route('notifications.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_sees_own_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));
        $other->notify(new AdminAnnouncementNotification($announcement));

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $this->assertSame(1, \DB::table('notifications')->where('notifiable_id', $user->id)->count());
    }

    public function test_unread_tab_filters_to_unread_only(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));

        // 既存通知を一つ既読に
        $user->unreadNotifications->first()?->markAsRead();
        $user->notify(new AdminAnnouncementNotification($announcement));

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertViewHas('tab', 'unread');
        $response->assertViewHas('notifications', fn ($paginator) => $paginator->total() === 1);
    }
}
