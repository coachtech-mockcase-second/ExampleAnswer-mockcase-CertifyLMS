<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadNotResolvedException;
use App\Models\QaThread;
use App\UseCases\QaThread\UnresolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnresolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolves_resolved_thread_and_clears_resolved_at(): void
    {
        $thread = QaThread::factory()->resolved()->create();

        $result = app(UnresolveAction::class)($thread);

        $this->assertSame(QaThreadStatus::Open, $result->status);
        $this->assertNull($result->resolved_at);
    }

    public function test_throws_when_already_open(): void
    {
        $thread = QaThread::factory()->unresolved()->create();

        $this->expectException(QaThreadNotResolvedException::class);

        app(UnresolveAction::class)($thread);
    }
}
