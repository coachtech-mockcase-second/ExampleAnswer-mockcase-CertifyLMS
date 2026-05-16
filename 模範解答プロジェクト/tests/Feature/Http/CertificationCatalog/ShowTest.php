<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CertificationCatalog;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->get(route('certifications.show', $cert));

        $response->assertOk();
        $response->assertViewIs('certifications.show');
    }

    public function test_student_gets_403_on_draft_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->get(route('certifications.show', $cert));

        $response->assertForbidden();
    }

    public function test_student_gets_403_on_archived_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($student)->get(route('certifications.show', $cert));

        $response->assertForbidden();
    }

    public function test_admin_can_view_draft_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->get(route('certifications.show', $cert));

        $response->assertOk();
    }
}
