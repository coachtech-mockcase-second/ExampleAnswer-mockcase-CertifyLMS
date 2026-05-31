<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Events\ChatMessageSent;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `POST /chat-rooms/{room}/messages` の検証。
 *
 * - body 必須 / 長さ上限
 * - 担当コーチ 0 件で 422 を返す
 * - 非 ChatMember は 403
 * - 編集 / 削除エンドポイントは存在しない(404)
 * - 送信成功で ChatMessageSent Event + ChatMessageReceivedNotification が発火する
 */
class StoreMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_send_message_and_dispatches_event_and_notification(): void
    {
        Event::fake([ChatMessageSent::class]);
        Notification::fake();

        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => 'こんにちは、相談したいです。']);

        $response->assertRedirect(route('chat.show', $room));
        $this->assertDatabaseHas('chat_messages', [
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
            'body' => 'こんにちは、相談したいです。',
        ]);

        Event::assertDispatched(ChatMessageSent::class);
        Notification::assertSentTo($coach, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_body_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => '']);

        $response->assertSessionHasErrors('body');
    }

    public function test_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();

        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => str_repeat('a', 2001)]);

        $response->assertSessionHasErrors('body');
    }

    public function test_returns_422_when_no_coach_assigned_via_json(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $response = $this->actingAs($student)
            ->postJson(route('chat.storeMessage', $room), ['body' => 'hello']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('chat_messages', ['chat_room_id' => $room->id]);
    }

    public function test_html_post_with_no_coach_redirects_back_with_flash_error(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $response = $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => 'hello']);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('chat_messages', ['chat_room_id' => $room->id]);
    }

    public function test_non_member_forbidden(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $stranger = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $response = $this->actingAs($stranger)
            ->post(route('chat.storeMessage', $room), ['body' => 'hi']);

        $response->assertForbidden();
    }

    public function test_admin_cannot_send_message(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $response = $this->actingAs($admin)
            ->post(route('chat.storeMessage', $room), ['body' => 'hi']);

        $response->assertForbidden();
    }

    public function test_no_edit_or_delete_endpoints(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        $messageId = (string) Str::ulid();

        $this->actingAs($student)
            ->put("/chat-rooms/{$room->id}/messages/{$messageId}", ['body' => 'x'])
            ->assertNotFound();

        $this->actingAs($student)
            ->delete("/chat-rooms/{$room->id}/messages/{$messageId}")
            ->assertNotFound();
    }
}
