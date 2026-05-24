<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaReply モデルのリレーションを検証する Unit テスト。
 * 2 リレーション (thread / user) を網羅する。Q&A スレッドへの返信。
 */
class QaReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_relation_returns_parent_thread(): void
    {
        // Arrange
        $thread = QaThread::factory()->unresolved()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        // Act
        $parent = $reply->thread;

        // Assert
        $this->assertTrue($parent->is($thread));
    }

    public function test_user_relation_returns_author_user(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $reply = QaReply::factory()->byUser($coach)->create();

        // Act
        $author = $reply->user;

        // Assert
        $this->assertTrue($author->is($coach));
    }
}
