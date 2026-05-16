<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\CertificationCategory;

use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_unused_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.certification-categories.destroy', $category));

        $response->assertRedirect(route('admin.certification-categories.index'));
        $this->assertSoftDeleted('certification_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_in_use(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();
        Certification::factory()->draft()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->delete(route('admin.certification-categories.destroy', $category));

        $response->assertStatus(409);
        $this->assertDatabaseHas('certification_categories', ['id' => $category->id, 'deleted_at' => null]);
    }
}
