<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_certificate(): void
    {
        $owner = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->get(route('certificates.show', $cert));

        $response->assertOk();
        $response->assertViewIs('certificates.show');
        $response->assertSee($cert->serial_no);
    }

    public function test_admin_can_view_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certificate::factory()->create();

        $response = $this->actingAs($admin)->get(route('certificates.show', $cert));

        $response->assertOk();
    }

    public function test_other_student_is_forbidden(): void
    {
        $owner = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $cert = Certificate::factory()->for($owner)->create();

        $response = $this->actingAs($stranger)->get(route('certificates.show', $cert));

        $response->assertForbidden();
    }
}
