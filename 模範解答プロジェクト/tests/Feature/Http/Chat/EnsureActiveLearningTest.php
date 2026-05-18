<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * graduated 受講生は chat ルート全般から弾かれることを検証する(`active-learning` middleware)。
 */
class EnsureActiveLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduated_student_forbidden_on_index(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)->get(route('chat.index'))->assertForbidden();
    }

    public function test_graduated_student_forbidden_on_show(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);

        $this->actingAs($student)->get(route('chat.show', $room))->assertForbidden();
    }
}
