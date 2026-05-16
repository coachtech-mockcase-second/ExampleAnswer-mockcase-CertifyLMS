<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\CertificationCategory;

use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.certification-categories.store'), [
            'name' => 'IT 系',
            'slug' => 'tech-cert',
            'sort_order' => 10,
        ]);

        $response->assertRedirect(route('admin.certification-categories.index'));
        $this->assertDatabaseHas('certification_categories', ['slug' => 'tech-cert']);
    }

    public function test_duplicate_slug_returns_error(): void
    {
        $admin = User::factory()->admin()->create();
        CertificationCategory::factory()->create(['slug' => 'tech-cert']);

        $response = $this->actingAs($admin)->post(route('admin.certification-categories.store'), [
            'name' => 'Duplicate',
            'slug' => 'tech-cert',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_coach_cannot_create(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.certification-categories.store'), [
            'name' => 'Hack',
            'slug' => 'hack',
        ]);

        $response->assertForbidden();
    }
}
