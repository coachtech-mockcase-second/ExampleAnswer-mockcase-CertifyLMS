<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_soft_delete(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->delete(route('ai-chat.conversations.destroy', $conv))
            ->assertRedirect(route('ai-chat.index'));

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conv->id]);
    }

    public function test_other_student_cannot_delete(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->delete(route('ai-chat.conversations.destroy', $conv))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $conv->id]);
    }
}
