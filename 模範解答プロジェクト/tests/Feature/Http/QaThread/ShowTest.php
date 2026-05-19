<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_student_can_view_thread_in_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee($thread->title);
    }

    public function test_student_cannot_view_thread_in_unpublished_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->draft()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_coach_can_view_thread_in_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $thread));

        $response->assertOk();
    }

    public function test_coach_cannot_view_thread_in_unassigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_soft_deleted_thread_returns_404(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $thread->delete();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread->id));

        $response->assertNotFound();
    }
}
