<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conv))
            ->assertOk();
    }

    public function test_other_student_cannot_view_someone_elses_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('ai-chat.conversations.show', $conv))
            ->assertForbidden();
    }

    public function test_admin_cannot_view_student_conversation(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($admin)
            ->get(route('ai-chat.conversations.show', $conv))
            ->assertForbidden();
    }
}
