<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminChatRoom;

use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_room(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $response = $this->actingAs($admin)->get(route('admin.chat-rooms.show', $room));

        $response->assertOk();
        $response->assertViewIs('admin.chat-rooms.show');
    }
}
