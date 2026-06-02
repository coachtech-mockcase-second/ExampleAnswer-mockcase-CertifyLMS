<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * StoreMessageAction の責務:
 *
 * - ChatMessage INSERT + 送信者の ChatMember.last_read_at = now() 更新
 * - DB::afterCommit() で ChatMessageSent broadcast を発火
 * - シグネチャは `__invoke(User, ChatRoom, array)`(E-3 撤回後の単一形態)
 */
class StoreMessageActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_insert_message_and_update_sender_last_read_at(): void
    {
        Event::fake([ChatMessageSent::class]);

        $sender = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($sender)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $senderMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $sender->id,
            'last_read_at' => null,
        ]);
        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
            'last_read_at' => null,
        ]);

        $message = app(StoreMessageAction::class)($sender, $room, ['body' => 'こんにちは']);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $message->id,
            'chat_room_id' => $room->id,
            'sender_user_id' => $sender->id,
            'body' => 'こんにちは',
        ]);
        $this->assertNotNull($senderMember->fresh()->last_read_at);

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_signature_is_user_chat_room_array(): void
    {
        $reflection = new \ReflectionMethod(StoreMessageAction::class, '__invoke');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame(User::class, $params[0]->getType()?->getName());
        $this->assertSame(ChatRoom::class, $params[1]->getType()?->getName());
        $this->assertSame('array', $params[2]->getType()?->getName());
    }
}
