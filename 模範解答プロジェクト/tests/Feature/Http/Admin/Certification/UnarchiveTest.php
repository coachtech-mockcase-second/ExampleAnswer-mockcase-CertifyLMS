<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unarchives_archived_to_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.unarchive', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $fresh = $cert->fresh();
        $this->assertSame('draft', $fresh->status->value);
        $this->assertNull($fresh->published_at);
        $this->assertNull($fresh->archived_at);
    }

    public function test_cannot_unarchive_published(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.unarchive', $cert));

        $response->assertStatus(409);
    }
}
