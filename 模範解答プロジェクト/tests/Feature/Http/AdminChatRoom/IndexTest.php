<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminChatRoom;

use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /admin/chat-rooms` の挙動を検証する。admin のみ閲覧可。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_all_chat_rooms(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        ChatRoom::factory()->for($enrollment)->create();

        $response = $this->actingAs($admin)->get(route('admin.chat-rooms.index'));

        $response->assertOk();
        $response->assertViewIs('admin.chat-rooms.index');
    }

    public function test_coach_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($coach)->get(route('admin.chat-rooms.index'))->assertForbidden();
    }

    public function test_student_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->actingAs($student)->get(route('admin.chat-rooms.index'))->assertForbidden();
    }
}
