<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_student_can_post_reply_in_published_certification(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create();
        $replier = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答本文',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replier->id,
            'body' => '回答本文',
        ]);
        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
    }

    public function test_coach_can_post_reply_in_assigned_certification(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'コーチからの回答',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
    }

    public function test_coach_cannot_post_reply_in_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当外への回答',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('qa_replies', ['qa_thread_id' => $thread->id]);
    }

    public function test_admin_cannot_post_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), [
            'body' => 'admin からの回答',
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_self_reply_does_not_send_notification(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($author)->post(route('qa-board.replies.store', $thread), [
            'body' => '自分への回答',
        ]);

        $response->assertRedirect();
        Notification::assertNotSentTo($author, QaReplyReceivedNotification::class);
        $this->assertDatabaseHas('qa_replies', ['qa_thread_id' => $thread->id, 'user_id' => $author->id]);
    }

    public function test_body_validation_failure_returns_422(): void
    {
        $replier = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($replier)->postJson(route('qa-board.replies.store', $thread), [
            'body' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }
}
