<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * `routes/channels.php` の `chat-room.{id}` チャネル認可コールバックを検証する。
 */
class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_subscribe_and_non_member_cannot(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $stranger = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $channels = require base_path('routes/channels.php');

        $callback = Broadcast::getChannels()['chat-room.{chatRoomId}'] ?? null;
        $this->assertNotNull($callback, 'chat-room.{chatRoomId} channel must be registered');

        $this->assertTrue($callback($student, $room->id));
        $this->assertFalse($callback($stranger, $room->id));
    }
}
