<?php

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_draft_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.certifications.destroy', $cert));

        $response->assertRedirect(route('admin.certifications.index'));
        $this->assertSoftDeleted('certifications', ['id' => $cert->id]);
    }

    public function test_cannot_delete_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->delete(route('admin.certifications.destroy', $cert));

        $response->assertStatus(409);
        $this->assertDatabaseHas('certifications', [
            'id' => $cert->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_delete_archived_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($admin)->delete(route('admin.certifications.destroy', $cert));

        $response->assertStatus(409);
    }

    public function test_coach_cannot_delete(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($coach)->delete(route('admin.certifications.destroy', $cert));

        $response->assertForbidden();
    }
}
