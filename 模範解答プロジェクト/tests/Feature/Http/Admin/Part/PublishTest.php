<?php

namespace Tests\Feature\Http\Admin\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_draft_part(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.parts.publish', $part))
            ->assertRedirect(route('admin.parts.show', $part));

        $part->refresh();
        $this->assertSame('published', $part->status->value);
        $this->assertNotNull($part->published_at);
    }

    public function test_cannot_publish_already_published(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.parts.publish', $part))
            ->assertStatus(409);
    }

    public function test_admin_can_unpublish_published_part(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.parts.unpublish', $part))
            ->assertRedirect(route('admin.parts.show', $part));

        $this->assertSame('draft', $part->fresh()->status->value);
    }
}
