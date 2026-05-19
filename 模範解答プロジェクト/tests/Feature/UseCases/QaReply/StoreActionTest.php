<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\UseCases\QaReply\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_reply_and_notifies_author_when_replier_is_different(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create();
        $replier = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        app(StoreAction::class)($thread, $replier, '回答本文');

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replier->id,
            'body' => '回答本文',
        ]);
        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
        Notification::assertNotSentTo($replier, QaReplyReceivedNotification::class);
    }

    public function test_self_reply_does_not_send_notification(): void
    {
        Notification::fake();

        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        app(StoreAction::class)($thread, $author, '自己回答');

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
        ]);
        Notification::assertNotSentTo($author, QaReplyReceivedNotification::class);
    }

    public function test_withdrawn_author_does_not_receive_notification(): void
    {
        Notification::fake();

        $author = User::factory()->student()->withdrawn()->create();
        $replier = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        app(StoreAction::class)($thread, $replier, '回答');

        Notification::assertNotSentTo($author, QaReplyReceivedNotification::class);
    }
}
