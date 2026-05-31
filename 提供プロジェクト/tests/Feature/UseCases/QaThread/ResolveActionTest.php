<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\UseCases\QaThread\ResolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_open_thread_and_sets_resolved_at(): void
    {
        $thread = QaThread::factory()->unresolved()->create();

        $result = app(ResolveAction::class)($thread);

        $this->assertSame(QaThreadStatus::Resolved, $result->status);
        $this->assertNotNull($result->resolved_at);
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    public function test_resolving_already_resolved_thread_is_noop_and_keeps_resolved_at(): void
    {
        $thread = QaThread::factory()->resolved()->create(['resolved_at' => now()->subDay()]);
        $originalResolvedAt = $thread->resolved_at->toDateTimeString();

        $result = app(ResolveAction::class)($thread);

        $this->assertSame(QaThreadStatus::Resolved, $result->status);
        $this->assertSame(
            $originalResolvedAt,
            $thread->fresh()->resolved_at->toDateTimeString(),
            '既に解決済のスレッドを再 resolve しても解決日時は更新されないはず',
        );
    }
}
