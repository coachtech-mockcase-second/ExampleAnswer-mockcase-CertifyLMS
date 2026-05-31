<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\ChatMemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatMemberSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_for_room_creates_student_and_assigned_coaches(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach1 = User::factory()->coach()->inProgress()->create();
        $coach2 = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();
        foreach ([$coach1, $coach2] as $coach) {
            $certification->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::create(['enrollment_id' => $enrollment->id, 'last_message_at' => null]);

        app(ChatMemberSyncService::class)->syncForRoom($room);

        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room->id, 'user_id' => $student->id]);
        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room->id, 'user_id' => $coach1->id]);
        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room->id, 'user_id' => $coach2->id]);
    }

    public function test_sync_for_room_creates_only_student_when_no_coach_assigned(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::create(['enrollment_id' => $enrollment->id, 'last_message_at' => null]);

        app(ChatMemberSyncService::class)->syncForRoom($room);

        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room->id, 'user_id' => $student->id]);
        $this->assertSame(1, ChatMember::query()->where('chat_room_id', $room->id)->count());
    }

    public function test_sync_for_certification_adds_member_to_all_existing_rooms(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $student1 = User::factory()->student()->inProgress()->create();
        $student2 = User::factory()->student()->inProgress()->create();

        $enrollment1 = Enrollment::factory()->for($student1)->for($certification)->create();
        $enrollment2 = Enrollment::factory()->for($student2)->for($certification)->create();

        $room1 = ChatRoom::create(['enrollment_id' => $enrollment1->id, 'last_message_at' => null]);
        $room2 = ChatRoom::create(['enrollment_id' => $enrollment2->id, 'last_message_at' => null]);

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ChatMemberSyncService::class)->syncForCertification($certification->fresh());

        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room1->id, 'user_id' => $coach->id]);
        $this->assertDatabaseHas('chat_members', ['chat_room_id' => $room2->id, 'user_id' => $coach->id]);
    }
}
