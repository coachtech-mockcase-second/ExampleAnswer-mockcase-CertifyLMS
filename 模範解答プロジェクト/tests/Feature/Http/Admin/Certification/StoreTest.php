<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        $category = CertificationCategory::factory()->create();

        return array_merge([
            'code' => 'CERT-NEW001',
            'category_id' => $category->id,
            'name' => '新規資格',
            'slug' => 'new-cert',
            'description' => '説明文',
            'difficulty' => 'intermediate',
            'passing_score' => 60,
            'total_questions' => 80,
            'exam_duration_minutes' => 150,
        ], $override);
    }

    public function test_admin_can_create_certification_as_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();

        $response = $this->actingAs($admin)->post(route('admin.certifications.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('certifications', [
            'code' => 'CERT-NEW001',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_duplicate_code_returns_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->draft()->create(['code' => 'CERT-DUP001']);

        $response = $this->actingAs($admin)->post(route('admin.certifications.store'), $this->payload(['code' => 'CERT-DUP001']));

        $response->assertSessionHasErrors('code');
    }

    public function test_passing_score_must_be_between_1_and_100(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.store'), $this->payload(['passing_score' => 0]))
            ->assertSessionHasErrors('passing_score');

        $this->actingAs($admin)
            ->post(route('admin.certifications.store'), $this->payload(['passing_score' => 101]))
            ->assertSessionHasErrors('passing_score');
    }

    public function test_coach_cannot_create_certification(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.certifications.store'), $this->payload());

        $response->assertForbidden();
    }
}
