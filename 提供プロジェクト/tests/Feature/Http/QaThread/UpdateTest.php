<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_title_and_body(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
            'certification_id' => $cert->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_other_student_cannot_update(): void
    {
        $author = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($other)->patch(route('qa-board.update', $thread), [
            'title' => 'やられた',
            'body' => 'やられた',
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_update(): void
    {
        $author = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($coach)->patch(route('qa-board.update', $thread), [
            'title' => 'コーチ改竄',
            'body' => '改竄',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_update(): void
    {
        $author = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->create();

        $response = $this->actingAs($admin)->patch(route('qa-board.update', $thread), [
            'title' => 'admin 改竄',
            'body' => '改竄',
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_resolved_thread_can_still_be_updated_by_author(): void
    {
        $author = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($cert)->byUser($author)->resolved()->create();

        $response = $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '解決後の編集',
            'body' => '修正本文',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '解決後の編集',
        ]);
    }
}
