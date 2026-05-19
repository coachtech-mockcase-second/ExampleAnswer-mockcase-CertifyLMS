<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\AdminAnnouncement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_and_update_own_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AdminAnnouncementNotification($announcement));
        $notification = $user->unreadNotifications->first();

        $this->assertTrue($user->can('view', $notification));
        $this->assertTrue($user->can('update', $notification));
    }

    public function test_user_cannot_view_or_update_others_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->dispatched()->create();
        $other->notify(new AdminAnnouncementNotification($announcement));
        $notification = $other->unreadNotifications->first();

        $this->assertFalse($user->can('view', $notification));
        $this->assertFalse($user->can('update', $notification));
    }
}
