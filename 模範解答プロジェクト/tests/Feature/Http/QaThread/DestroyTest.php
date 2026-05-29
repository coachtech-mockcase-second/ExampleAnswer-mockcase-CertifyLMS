<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_when_no_replies(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($author)->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_author_delete_with_replies_returns_409(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();
        QaReply::factory()->forThread($thread)->create();

        $response = $this->actingAs($author)->deleteJson(route('qa-board.destroy', $thread));

        $this->assertSame(409, $response->status(), '回答ありスレッドの投稿者削除は DestroyAction の状態ガードで 409');
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_other_student_cannot_delete(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($other)->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();
    }

    public function test_coach_cannot_delete(): void
    {
        $author = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($coach)->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();
    }
}
