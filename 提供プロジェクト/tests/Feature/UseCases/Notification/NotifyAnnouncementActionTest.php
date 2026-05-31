<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use App\UseCases\Notification\NotifyAnnouncementAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyAnnouncementActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_notification_to_in_progress_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->create();

        $result = app(NotifyAnnouncementAction::class)($announcement, $student);

        $this->assertTrue($result);
        Notification::assertSentTo($student, AnnouncementNotification::class);
    }

    public function test_skips_withdrawn_recipient(): void
    {
        Notification::fake();
        $student = User::factory()->student()->withdrawn()->create();
        $announcement = Announcement::factory()->allStudents()->create();

        $result = app(NotifyAnnouncementAction::class)($announcement, $student);

        $this->assertFalse($result);
        Notification::assertNothingSent();
    }

    public function test_skips_graduated_recipient(): void
    {
        Notification::fake();
        $student = User::factory()->student()->graduated()->create();
        $announcement = Announcement::factory()->allStudents()->create();

        $result = app(NotifyAnnouncementAction::class)($announcement, $student);

        $this->assertFalse($result);
        Notification::assertNothingSent();
    }
}
