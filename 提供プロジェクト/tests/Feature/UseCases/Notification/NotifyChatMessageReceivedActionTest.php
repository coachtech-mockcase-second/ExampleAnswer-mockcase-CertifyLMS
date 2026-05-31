<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\UseCases\Notification\NotifyChatMessageReceivedAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * v3 双方向化 + コーチ間 DB only 検証。
 */
class NotifyChatMessageReceivedActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sender_sends_db_and_mail_to_all_coaches(): void
    {
        Notification::fake();
        [$room, $student, $coach1, $coach2] = $this->makeRoom(coachCount: 2);
        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
            'body' => 'こんにちは',
        ]);

        app(NotifyChatMessageReceivedAction::class)($message);

        Notification::assertSentTo($coach1, ChatMessageReceivedNotification::class, function ($notif) {
            return $notif->mailEnabled === true;
        });
        Notification::assertSentTo($coach2, ChatMessageReceivedNotification::class, function ($notif) {
            return $notif->mailEnabled === true;
        });
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_coach_sender_sends_db_and_mail_to_student_and_db_only_to_other_coaches(): void
    {
        Notification::fake();
        [$room, $student, $coach1, $coach2] = $this->makeRoom(coachCount: 2);
        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $coach1->id,
            'body' => 'お疲れさまです',
        ]);

        app(NotifyChatMessageReceivedAction::class)($message);

        Notification::assertSentTo($student, ChatMessageReceivedNotification::class, function ($notif) {
            return $notif->mailEnabled === true;
        });
        Notification::assertSentTo($coach2, ChatMessageReceivedNotification::class, function ($notif) {
            return $notif->mailEnabled === false;
        });
        Notification::assertNotSentTo($coach1, ChatMessageReceivedNotification::class);
    }

    public function test_withdrawn_recipient_is_skipped(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->withdrawn()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $message = ChatMessage::factory()->create([
            'chat_room_id' => $room->id,
            'sender_user_id' => $student->id,
            'body' => 'hi',
        ]);

        app(NotifyChatMessageReceivedAction::class)($message);

        Notification::assertNothingSent();
    }

    /**
     * @return array{ChatRoom, User, User, User}
     */
    private function makeRoom(int $coachCount = 2): array
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $coaches = [];
        for ($i = 0; $i < $coachCount; $i++) {
            $coach = User::factory()->coach()->inProgress()->create();
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);
            $coaches[] = $coach;
        }

        return [$room, $student, $coaches[0], $coaches[1] ?? new AnonymousNotifiable];
    }
}
