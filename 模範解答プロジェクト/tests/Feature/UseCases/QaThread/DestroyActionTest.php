<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadHasRepliesException;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroys_thread_when_no_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();

        // Act
        app(DestroyAction::class)($thread, $student);

        // Assert
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_throws_exception_when_replies_exist(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();
        QaReply::factory()->forThread($thread)->create();

        // Act & Assert
        $this->expectException(QaThreadHasRepliesException::class);

        try {
            app(DestroyAction::class)($thread, $student);
        } finally {
            $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
        }
    }

    public function test_admin_can_destroy_thread_with_replies(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create();

        // Act
        app(DestroyAction::class)($thread, $admin);

        // Assert
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['qa_thread_id' => $thread->id]);
    }
}
