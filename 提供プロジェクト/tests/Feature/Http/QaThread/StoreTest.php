<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_post_thread(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '質問タイトル',
            'body' => '質問本文です。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $cert->id,
            'user_id' => $student->id,
            'title' => '質問タイトル',
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }

    public function test_coach_cannot_post_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_post_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $this->assertContains($response->status(), [403, 404], 'admin はそもそも /qa-board ルートにアクセスできない (role middleware で 403、または route 不在)');
    }

    public function test_unpublished_certification_rejected_with_422(): void
    {
        $student = User::factory()->student()->create();
        $draft = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $draft->id,
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['certification_id']);
    }

    public function test_full_width_space_only_title_rejected(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '   ',
            'body' => '本文',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_body_max_length_5000(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => 'タイトル',
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }
}
