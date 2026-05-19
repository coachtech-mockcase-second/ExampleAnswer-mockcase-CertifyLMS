<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_body(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $response = $this->actingAs($author)->patch(route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]), [
            'body' => '更新後の回答',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答',
        ]);
    }

    public function test_other_user_cannot_update(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $response = $this->actingAs($other)->patch(route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]), [
            'body' => '改竄',
        ]);

        $response->assertForbidden();
    }

    public function test_body_validation_failure_returns_422(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $response = $this->actingAs($author)->patchJson(route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]), [
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422);
    }
}
