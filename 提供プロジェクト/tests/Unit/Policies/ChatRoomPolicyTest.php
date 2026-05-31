<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Policies\ChatRoomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatRoomPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_room(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $room = ChatRoom::factory()->for(Enrollment::factory())->create();

        $this->assertTrue((new ChatRoomPolicy)->view($admin, $room));
    }

    public function test_member_can_view_but_non_member_cannot(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $room = ChatRoom::factory()->for(Enrollment::factory())->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $policy = new ChatRoomPolicy;
        $this->assertTrue($policy->view($student, $room));
        $this->assertFalse($policy->view($other, $room));
    }

    public function test_admin_cannot_send_message(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $room = ChatRoom::factory()->for(Enrollment::factory())->create();

        $this->assertFalse((new ChatRoomPolicy)->sendMessage($admin, $room));
    }

    public function test_send_message_requires_at_least_one_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $policy = new ChatRoomPolicy;
        $this->assertFalse($policy->sendMessage($student, $room->fresh()), 'コーチ未割当時は送信不可');

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($policy->sendMessage($student, $room->fresh()), 'コーチ割当後は送信可');
    }

    public function test_send_message_for_enrollment_method_does_not_exist(): void
    {
        $this->assertFalse(
            method_exists(ChatRoomPolicy::class, 'sendMessageForEnrollment'),
            'E-3 撤回: ChatRoom eager 生成で Enrollment ベース認可は不要',
        );
    }
}
