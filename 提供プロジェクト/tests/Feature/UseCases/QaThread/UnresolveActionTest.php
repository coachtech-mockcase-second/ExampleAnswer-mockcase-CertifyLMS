<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
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

    public function test_unresolving_already_open_thread_is_noop(): void
    {
        $thread = QaThread::factory()->unresolved()->create();

        $result = app(UnresolveAction::class)($thread);

        $this->assertSame(QaThreadStatus::Open, $result->status);
        $this->assertNull($result->resolved_at);
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }
}
