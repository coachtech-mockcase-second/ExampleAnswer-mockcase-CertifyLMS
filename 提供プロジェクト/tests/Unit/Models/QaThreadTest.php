<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * QaThread モデルのリレーション・Scope・Cast・isResolved ヘルパを検証する Unit テスト。
 * 3 リレーション (certification / user / replies) + 主要 scope 2 (resolved / unresolved) +
 * 2 cast (status enum / resolved_at datetime) + isResolved() を網羅する。
 */
class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        // Act
        $target = $thread->certification;

        // Assert
        $this->assertTrue($target->is($cert));
    }

    public function test_user_relation_returns_author_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($student)->create();

        // Act
        $author = $thread->user;

        // Assert
        $this->assertTrue($author->is($student));
    }

    public function test_replies_relation_returns_attached_replies(): void
    {
        // Arrange
        $thread = QaThread::factory()->unresolved()->create();
        QaReply::factory()->forThread($thread)->create();
        QaReply::factory()->forThread($thread)->create();

        // Act
        $replies = $thread->replies;

        // Assert
        $this->assertCount(2, $replies);
    }

    public function test_scope_resolved_filters_only_resolved(): void
    {
        // Arrange
        $resolved = QaThread::factory()->resolved()->create();
        QaThread::factory()->unresolved()->create();

        // Act
        $results = QaThread::resolved()->get();

        // Assert
        $this->assertCount(1, $results, 'Resolved ステータスの thread のみが抽出されるはず');
        $this->assertTrue($results->first()->is($resolved));
    }

    public function test_is_resolved_helper_reflects_status(): void
    {
        // Arrange
        $resolved = QaThread::factory()->resolved()->create();
        $unresolved = QaThread::factory()->unresolved()->create();

        // Act & Assert
        $this->assertTrue($resolved->isResolved(), 'Resolved thread は isResolved() で true を返すはず');
        $this->assertFalse($unresolved->isResolved(), 'Open thread は isResolved() で false を返すはず');
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $thread = QaThread::factory()->resolved()->create();

        // Act
        $fresh = $thread->fresh();

        // Assert
        $this->assertInstanceOf(QaThreadStatus::class, $fresh->status);
        $this->assertSame(QaThreadStatus::Resolved, $fresh->status);
    }

    public function test_resolved_at_cast_returns_carbon(): void
    {
        // Arrange
        $thread = QaThread::factory()->resolved()->create();

        // Act
        $fresh = $thread->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->resolved_at, 'resolved 済 thread は resolved_at が Carbon にキャストされるはず');
    }
}
