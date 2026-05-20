<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_show(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched(10)->create([
            'title' => 'メンテナンスのお知らせ',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.announcements.show', $announcement));

        $response->assertOk();
        $response->assertViewIs('announcement.management.show');
        $response->assertViewHas('announcement', fn ($a) => $a->id === $announcement->id);
        $response->assertSeeText('メンテナンスのお知らせ');
        $response->assertSeeText('10');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $announcement = Announcement::factory()->allStudents()->dispatched()->create();

        $response = $this->actingAs($coach)->get(route('admin.announcements.show', $announcement));

        $response->assertForbidden();
    }
}
