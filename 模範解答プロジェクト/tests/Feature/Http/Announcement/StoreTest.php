<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Announcement\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_to_all_in_progress_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $students = User::factory()->student()->inProgress()->count(3)->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本日深夜にメンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($students as $student) {
            Notification::assertSentTo($student, AnnouncementNotification::class);
        }
        Notification::assertNotSentTo($withdrawn, AnnouncementNotification::class);

        $this->assertDatabaseHas('announcements', [
            'title' => 'メンテナンスのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 3,
        ]);
    }

    public function test_admin_can_target_certification(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $enrolledStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($enrolledStudent)->for($cert)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $otherStudent = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '受講者向け',
            'body' => '〜',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);

        $response->assertRedirect();
        Notification::assertSentTo($enrolledStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($otherStudent, AnnouncementNotification::class);
    }

    public function test_admin_can_target_single_user(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別連絡',
            'body' => '〜',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);

        $response->assertRedirect();
        Notification::assertSentTo($student, AnnouncementNotification::class);
        Notification::assertNotSentTo($other, AnnouncementNotification::class);
    }

    public function test_target_consistency_violation_returns_422(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), [
            'title' => '不整合',
            'body' => '〜',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => '01HABCDEFGHIJKMNOPQRSTUVWX',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->post(route('admin.announcements.store'), [
            'title' => '違反',
            'body' => '〜',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertForbidden();
    }
}
