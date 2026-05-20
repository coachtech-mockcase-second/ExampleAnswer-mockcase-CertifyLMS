<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Dashboard;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardSidebarConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notifications_badge_is_always_zero(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $this->actingAs($admin);

        $badges = $this->composeBadges();

        $this->assertSame(0, $badges['notifications']);
    }

    public function test_pending_completions_key_is_removed_from_composer(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->actingAs($student);

        $badges = $this->composeBadges();

        $this->assertArrayNotHasKey('pendingCompletions', $badges);
    }

    public function test_student_today_meetings_badge_matches_database_count(): void
    {
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->withPlan($plan)->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        Meeting::factory()->state([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved,
            'scheduled_at' => today()->setHour(15),
        ])->create();
        Meeting::factory()->state([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved,
            'scheduled_at' => now()->addDays(3),
        ])->create();

        $this->actingAs($student);
        $badges = $this->composeBadges();

        $this->assertSame(1, $badges['todayMeetings']);
    }

    public function test_coach_today_meetings_badge_matches_database_count(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($cert)->for($student)->learning()->create();

        Meeting::factory()->state([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved,
            'scheduled_at' => today()->setHour(15),
        ])->create();

        $this->actingAs($coach);
        $badges = $this->composeBadges();

        $this->assertSame(1, $badges['todayMeetings']);
    }

    public function test_notifications_badge_matches_unread_count_for_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $student->notifications()->createMany([
            ['id' => (string) Str::ulid(), 'type' => 'TestNotification', 'data' => [], 'read_at' => null],
            ['id' => (string) Str::ulid(), 'type' => 'TestNotification', 'data' => [], 'read_at' => now()],
        ]);

        $this->actingAs($student);
        $badges = $this->composeBadges();

        $this->assertSame(1, $badges['notifications']);
    }

    private function composeBadges(): array
    {
        $composer = app(SidebarBadgeComposer::class);
        $view = View::make('placeholders.coming-soon', ['feature' => 'dashboard']);
        $composer->compose($view);

        return $view->getData()['sidebarBadges'];
    }

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
