<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_soft_delete_own_reply(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->resolved()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();
        $resolvedAt = $thread->resolved_at;

        $response = $this->actingAs($author)->delete(route('qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status, 'スレッド状態は変わらない');
        $this->assertEquals($resolvedAt?->toIso8601String(), $thread->resolved_at?->toIso8601String(), 'resolved_at は不変');
    }

    public function test_other_student_cannot_delete(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $response = $this->actingAs($other)->delete(route('qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]));

        $response->assertForbidden();
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }

    public function test_coach_other_than_author_cannot_delete(): void
    {
        $author = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($author)->create();

        $response = $this->actingAs($coach)->delete(route('qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]));

        $response->assertForbidden();
    }
}
