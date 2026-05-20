<?php

declare(strict_types=1);

namespace Tests\Unit\ViewComposers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use App\View\Composers\NotificationBadgeComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\View;
use Tests\TestCase;

class NotificationBadgeComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_unread_count_for_authenticated_user(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();
        $user->notify(new AnnouncementNotification($announcement));
        $user->notify(new AnnouncementNotification($announcement));

        $this->actingAs($user);

        $captured = null;
        $view = $this->mockView(function ($name, $value) use (&$captured): void {
            if ($name === 'notificationBadge') {
                $captured = $value;
            }
        });

        (new NotificationBadgeComposer)->compose($view);

        $this->assertSame(2, $captured);
    }

    public function test_returns_zero_when_unauthenticated(): void
    {
        $captured = null;
        $view = $this->mockView(function ($name, $value) use (&$captured): void {
            if ($name === 'notificationBadge') {
                $captured = $value;
            }
        });

        (new NotificationBadgeComposer)->compose($view);

        $this->assertSame(0, $captured);
    }

    private function mockView(callable $onWith): View
    {
        $view = $this->createMock(View::class);
        $view->method('with')->willReturnCallback(function ($name, $value = null) use ($onWith) {
            $onWith($name, $value);

            return $name;
        });

        return $view;
    }
}
