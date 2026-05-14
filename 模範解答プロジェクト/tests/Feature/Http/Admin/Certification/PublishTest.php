<?php

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_draft_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.publish', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertSame('published', $cert->fresh()->status->value);
        $this->assertNotNull($cert->fresh()->published_at);
    }

    public function test_cannot_publish_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.publish', $cert));

        $response->assertStatus(409);
    }

    public function test_cannot_publish_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.publish', $cert));

        $response->assertStatus(409);
    }

    public function test_coach_cannot_publish(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($coach)->post(route('admin.certifications.publish', $cert));

        $response->assertForbidden();
    }
}
