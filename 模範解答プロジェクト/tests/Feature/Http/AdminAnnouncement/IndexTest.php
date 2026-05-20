<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminAnnouncement;

use App\Models\AdminAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        AdminAnnouncement::factory()->allStudents()->dispatched(5)->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertViewIs('admin.announcements.index');
        $response->assertViewHas('announcements', fn ($paginator) => $paginator->total() === 3);
    }

    public function test_coach_cannot_view(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('admin.announcements.index'));

        $response->assertForbidden();
    }

    public function test_student_cannot_view(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('admin.announcements.index'));

        $response->assertForbidden();
    }
}
