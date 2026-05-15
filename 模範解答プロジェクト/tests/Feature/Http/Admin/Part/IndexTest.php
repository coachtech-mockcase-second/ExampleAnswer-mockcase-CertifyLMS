<?php

namespace Tests\Feature\Http\Admin\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_view_parts_for_any_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        Part::factory()->forCertification($cert)->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.certifications.parts.index', $cert))
            ->assertOk()
            ->assertViewIs('admin.contents.parts.index')
            ->assertViewHas('parts');
    }

    public function test_assigned_coach_can_view_parts(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        Part::factory()->forCertification($cert)->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.parts.index', $cert))
            ->assertOk();
    }

    public function test_non_assigned_coach_cannot_view_parts(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.parts.index', $cert))
            ->assertForbidden();
    }

    public function test_student_cannot_view_admin_parts(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->get(route('admin.certifications.parts.index', $cert))
            ->assertForbidden();
    }
}
