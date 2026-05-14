<?php

namespace Tests\Feature\Http\CertificationCatalog;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_only_published_certifications_in_catalog(): void
    {
        $student = User::factory()->student()->create();
        Certification::factory()->draft()->create(['name' => 'Draft Cert']);
        Certification::factory()->archived()->create(['name' => 'Archived Cert']);
        Certification::factory()->published()->create(['name' => 'Published Cert']);

        $response = $this->actingAs($student)->get(route('certifications.index'));

        $response->assertOk();
        $response->assertViewIs('certifications.index');
        $response->assertSee('Published Cert');
        $response->assertDontSee('Draft Cert');
        $response->assertDontSee('Archived Cert');
    }

    public function test_coach_can_view_catalog(): void
    {
        $coach = User::factory()->coach()->create();
        Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->get(route('certifications.index'));

        $response->assertOk();
    }

    public function test_admin_can_view_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->get(route('certifications.index'));

        $response->assertOk();
    }

    public function test_enrolled_tab_only_shows_student_own_enrolled_certifications(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $myCert = Certification::factory()->published()->create(['name' => 'My Enrolled Cert']);
        $otherCert = Certification::factory()->published()->create(['name' => 'Other Cert']);

        Enrollment::factory()->learning()->create(['user_id' => $student->id, 'certification_id' => $myCert->id]);
        Enrollment::factory()->learning()->create(['user_id' => $other->id, 'certification_id' => $otherCert->id]);

        $response = $this->actingAs($student)->get(route('certifications.index', ['tab' => 'enrolled']));

        $response->assertOk();
        $response->assertSee('My Enrolled Cert');
        $response->assertDontSee('Other Cert');
    }

    public function test_guest_cannot_access(): void
    {
        $response = $this->get(route('certifications.index'));

        $response->assertRedirect(route('login'));
    }
}
