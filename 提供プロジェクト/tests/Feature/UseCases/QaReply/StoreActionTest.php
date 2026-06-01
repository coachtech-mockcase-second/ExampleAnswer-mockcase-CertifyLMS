<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaReply\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_reply_for_thread(): void
    {
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
    }

    public function test_self_reply_is_inserted(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        app(StoreAction::class)($thread, $author, '自己回答');

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
        ]);
    }
}
