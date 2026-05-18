<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Events\CertificationCoachAttached;
use App\Listeners\SyncChatMembersOnCoachAssignmentChanged;
use App\Models\Certification;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\ChatMemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 担当コーチ追加時に Listener が起動し、該当資格の全 ChatRoom に ChatMember が追加されることを検証する。
 */
class CoachAssignmentChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_syncs_chat_members_when_coach_attached(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $newCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::create([
            'enrollment_id' => $enrollment->id,
            'last_message_at' => null,
        ]);

        $this->assertDatabaseMissing('chat_members', [
            'chat_room_id' => $room->id,
            'user_id' => $newCoach->id,
        ]);

        $certification->coaches()->attach($newCoach->id, [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SyncChatMembersOnCoachAssignmentChanged(app(ChatMemberSyncService::class)))
            ->handle(new CertificationCoachAttached($certification->fresh(), $newCoach, $admin));

        $this->assertDatabaseHas('chat_members', [
            'chat_room_id' => $room->id,
            'user_id' => $newCoach->id,
        ]);
    }
}
