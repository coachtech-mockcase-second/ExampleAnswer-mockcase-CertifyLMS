<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AdminQaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_thread_with_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        QaReply::factory()->forThread($thread)->count(3)->create();

        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertSoftDeleted('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseHas('qa_replies', ['qa_thread_id' => $thread->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_delete(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        foreach ([$coach, $student] as $denied) {
            $response = $this->actingAs($denied)->delete(route('admin.qa-board.destroy', $thread));
            $this->assertContains($response->status(), [403, 404]);
            $this->assertDatabaseHas('qa_threads', ['id' => $thread->id, 'deleted_at' => null]);
        }
    }
}
