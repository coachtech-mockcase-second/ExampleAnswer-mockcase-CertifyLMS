<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_title(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->patch(route('ai-chat.conversations.update', $conv), ['title' => '新タイトル'])
            ->assertRedirect();

        $this->assertSame('新タイトル', $conv->fresh()->title);
    }

    public function test_rejects_empty_title(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create(['title' => 'old']);

        $this->actingAs($student)
            ->patch(route('ai-chat.conversations.update', $conv), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame('old', $conv->fresh()->title);
    }

    public function test_other_student_cannot_update(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->patch(route('ai-chat.conversations.update', $conv), ['title' => 'hijacked'])
            ->assertForbidden();
    }
}
