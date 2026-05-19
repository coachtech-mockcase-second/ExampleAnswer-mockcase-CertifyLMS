<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaReply;

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

    public function test_admin_can_soft_delete_reply_and_thread_status_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->resolved()->create();
        $reply = QaReply::factory()->forThread($thread)->create();
        $originalResolvedAt = $thread->resolved_at;

        $response = $this->actingAs($admin)->delete(route('admin.qa-board.replies.destroy', $reply));

        $response->assertRedirect();
        $this->assertSoftDeleted('qa_replies', ['id' => $reply->id]);

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertEquals($originalResolvedAt?->toIso8601String(), $thread->resolved_at?->toIso8601String());
    }

    public function test_non_admin_cannot_delete(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        foreach ([$coach, $student] as $denied) {
            $response = $this->actingAs($denied)->delete(route('admin.qa-board.replies.destroy', $reply));
            $this->assertContains($response->status(), [403, 404]);
        }
    }
}
