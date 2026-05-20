<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AdminAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnnouncementPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->create();

        $this->assertTrue($admin->can('viewAny', AdminAnnouncement::class));
        $this->assertTrue($admin->can('create', AdminAnnouncement::class));
        $this->assertTrue($admin->can('view', $announcement));
    }

    public function test_coach_cannot(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->create();

        $this->assertFalse($coach->can('viewAny', AdminAnnouncement::class));
        $this->assertFalse($coach->can('create', AdminAnnouncement::class));
        $this->assertFalse($coach->can('view', $announcement));
    }

    public function test_student_cannot(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $announcement = AdminAnnouncement::factory()->allStudents()->create();

        $this->assertFalse($student->can('viewAny', AdminAnnouncement::class));
        $this->assertFalse($student->can('create', AdminAnnouncement::class));
        $this->assertFalse($student->can('view', $announcement));
    }
}
