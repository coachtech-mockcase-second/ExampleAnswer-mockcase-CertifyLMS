<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create([
            'name' => 'Old Name',
            'difficulty' => 'beginner',
        ]);

        $payload = [
            'name' => 'New Name',
            'category_id' => $cert->category_id,
            'difficulty' => 'advanced',
            'description' => '更新後の説明',
        ];

        $response = $this->actingAs($admin)->put(route('admin.certifications.update', $cert), $payload);

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertDatabaseHas('certifications', [
            'id' => $cert->id,
            'name' => 'New Name',
            'difficulty' => 'advanced',
            'description' => '更新後の説明',
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_is_unchanged_even_if_payload_includes_status(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $payload = [
            'name' => $cert->name,
            'category_id' => $cert->category_id,
            'difficulty' => $cert->difficulty->value,
            'description' => $cert->description,
            'status' => 'draft',
        ];

        $this->actingAs($admin)->put(route('admin.certifications.update', $cert), $payload);

        $this->assertSame('published', $cert->fresh()->status->value);
    }

    public function test_coach_cannot_update(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($coach)->put(route('admin.certifications.update', $cert), [
            'name' => 'Hack',
            'category_id' => $cert->category_id,
            'difficulty' => $cert->difficulty->value,
        ]);

        $response->assertForbidden();
    }
}
