<?php

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_archives_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.archive', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertSame('archived', $cert->fresh()->status->value);
        $this->assertNotNull($cert->fresh()->archived_at);
    }

    public function test_cannot_archive_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.archive', $cert));

        $response->assertStatus(409);
    }
}
