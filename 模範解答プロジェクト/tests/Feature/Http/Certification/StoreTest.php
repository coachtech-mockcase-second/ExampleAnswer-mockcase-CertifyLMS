<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        $category = CertificationCategory::factory()->create();

        return array_merge([
            'name' => '新規資格',
            'category_id' => $category->id,
            'difficulty' => 'intermediate',
            'description' => '説明文',
        ], $override);
    }

    public function test_admin_can_create_certification_as_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();

        $response = $this->actingAs($admin)->post(route('admin.certifications.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('certifications', [
            'name' => '新規資格',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.certifications.store'), $this->payload(['category_id' => '']))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($admin)
            ->post(route('admin.certifications.store'), $this->payload(['difficulty' => 'invalid']))
            ->assertSessionHasErrors('difficulty');
    }

    public function test_coach_cannot_create_certification(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.certifications.store'), $this->payload());

        $response->assertForbidden();
    }
}
