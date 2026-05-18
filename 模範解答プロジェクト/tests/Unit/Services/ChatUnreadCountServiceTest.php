<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUnreadCountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_count_in_room_excludes_own_and_pre_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
            'last_read_at' => null,
        ]);

        ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach->id,
        ]);
        ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach->id,
        ]);
        ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
        ]);

        $count = app(ChatUnreadCountService::class)->messageCountInRoom($room, $student);

        $this->assertSame(2, $count);
    }

    public function test_room_count_for_user_counts_only_rooms_with_unread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $enrollmentA = Enrollment::factory()->for($student)->create();
        $roomA = ChatRoom::factory()->for($enrollmentA)->create();
        ChatMember::factory()->create([
            'chat_room_id' => $roomA->id,
            'user_id' => $student->id,
            'last_read_at' => null,
        ]);
        ChatMessage::factory()->create([
            'chat_room_id' => $roomA->id,
            'sender_user_id' => $coach->id,
        ]);

        $enrollmentB = Enrollment::factory()->for($student)->create();
        $roomB = ChatRoom::factory()->for($enrollmentB)->create();
        ChatMember::factory()->create([
            'chat_room_id' => $roomB->id,
            'user_id' => $student->id,
            'last_read_at' => now(),
        ]);

        $count = app(ChatUnreadCountService::class)->roomCountForUser($student);

        $this->assertSame(1, $count);
    }
}
