<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_resolve_then_unresolve(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->unresolved()->create();

        $resolve = $this->actingAs($author)->post(route('qa-board.resolve', $thread));
        $resolve->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);

        $unresolve = $this->actingAs($author)->post(route('qa-board.unresolve', $thread));
        $unresolve->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_double_resolve_returns_409(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->resolved()->create();

        $response = $this->actingAs($author)->postJson(route('qa-board.resolve', $thread));

        $this->assertSame(409, $response->status());
    }

    public function test_unresolve_open_thread_returns_409(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->unresolved()->create();

        $response = $this->actingAs($author)->postJson(route('qa-board.unresolve', $thread));

        $this->assertSame(409, $response->status());
    }

    public function test_non_author_cannot_resolve(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->unresolved()->create();

        foreach ([$other, $coach] as $denied) {
            $response = $this->actingAs($denied)->post(route('qa-board.resolve', $thread));
            $response->assertForbidden();
        }
        // admin は role:student,coach Middleware で 403 (routes 階層で先に弾かれる)
        $adminResp = $this->actingAs($admin)->post(route('qa-board.resolve', $thread));
        $this->assertContains($adminResp->status(), [403, 404]);
    }
}
