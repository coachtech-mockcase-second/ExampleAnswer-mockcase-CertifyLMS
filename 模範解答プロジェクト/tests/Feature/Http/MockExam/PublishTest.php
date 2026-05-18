<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExam;

use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_fails_when_no_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.publish', $mockExam))
            ->assertStatus(409);

        $this->assertFalse($mockExam->refresh()->is_published);
    }

    public function test_publish_succeeds_with_at_least_one_question(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create();
        MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();

        $this->actingAs($admin)
            ->post(route('admin.mock-exams.publish', $mockExam))
            ->assertRedirect();

        $mockExam->refresh();
        $this->assertTrue($mockExam->is_published);
        $this->assertNotNull($mockExam->published_at);
    }

    public function test_publish_fails_when_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->published()->create();
        MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.publish', $mockExam))
            ->assertStatus(409);
    }

    public function test_unpublish_succeeds_when_published(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.mock-exams.unpublish', $mockExam))
            ->assertRedirect();

        $mockExam->refresh();
        $this->assertFalse($mockExam->is_published);
        $this->assertNull($mockExam->published_at);
    }

    public function test_unpublish_fails_when_already_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.unpublish', $mockExam))
            ->assertStatus(409);
    }
}
