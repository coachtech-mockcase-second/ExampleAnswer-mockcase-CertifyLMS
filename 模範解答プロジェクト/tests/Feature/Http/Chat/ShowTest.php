<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /chat-rooms/{room}` の詳細画面と、viewer 個人別 `last_read_at` 更新を検証する。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_member_can_open_room_and_only_own_last_read_at_updates(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coachA = User::factory()->coach()->inProgress()->create();
        $coachB = User::factory()->coach()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        $coachAMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coachA->id,
            'last_read_at' => null,
        ]);
        $coachBMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coachB->id,
            'last_read_at' => null,
        ]);

        ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
        ]);

        $this->actingAs($coachA)->get(route('chat.show', $room))->assertOk();

        $this->assertNotNull($coachAMember->fresh()->last_read_at);
        $this->assertNull($coachBMember->fresh()->last_read_at, '他コーチの last_read_at は影響を受けてはならない');
    }

    public function test_non_member_cannot_view_room(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $stranger = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $this->actingAs($stranger)->get(route('chat.show', $room))->assertForbidden();
    }

    public function test_admin_can_view_via_admin_route_without_updating_last_read_at(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $studentMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
            'last_read_at' => null,
        ]);
        $coachMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
            'last_read_at' => null,
        ]);

        $this->actingAs($admin)->get(route('admin.chat-rooms.show', $room))->assertOk();

        $this->assertNull($studentMember->fresh()->last_read_at);
        $this->assertNull($coachMember->fresh()->last_read_at);
    }
}
