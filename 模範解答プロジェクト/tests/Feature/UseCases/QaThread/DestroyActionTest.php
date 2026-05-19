<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadHasRepliesException;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroys_thread_when_no_replies(): void
    {
        $thread = QaThread::factory()->create();

        app(DestroyAction::class)($thread);

        $this->assertSoftDeleted('qa_threads', ['id' => $thread->id]);
    }

    public function test_throws_exception_when_replies_exist(): void
    {
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create();

        $this->expectException(QaThreadHasRepliesException::class);

        try {
            app(DestroyAction::class)($thread);
        } finally {
            $this->assertDatabaseHas('qa_threads', ['id' => $thread->id, 'deleted_at' => null]);
        }
    }

    public function test_throws_exception_when_only_soft_deleted_replies_exist(): void
    {
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();
        $reply->delete();

        $this->expectException(QaThreadHasRepliesException::class);

        app(DestroyAction::class)($thread);
    }
}
