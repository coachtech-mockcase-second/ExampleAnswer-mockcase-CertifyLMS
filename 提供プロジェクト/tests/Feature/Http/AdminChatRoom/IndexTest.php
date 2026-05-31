<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminChatRoom;

use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `GET /admin/chat-rooms` の挙動を検証する。admin のみ閲覧可で、最新ルーム redirect。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_redirects_to_latest_chat_room(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $older = ChatRoom::factory()
            ->for(Enrollment::factory())
            ->withMessageAt(Carbon::now()->subHours(3))
            ->create();
        $newer = ChatRoom::factory()
            ->for(Enrollment::factory())
            ->withMessageAt(Carbon::now()->subMinutes(5))
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.chat-rooms.index'))
            ->assertRedirect(route('admin.chat-rooms.show', $newer));

        $this->assertNotNull($older); // 退避: 未使用警告抑止
    }

    public function test_admin_sees_empty_state_when_no_rooms_match(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->get(route('admin.chat-rooms.index'))
            ->assertOk()
            ->assertViewIs('chat-room.empty-state');
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
