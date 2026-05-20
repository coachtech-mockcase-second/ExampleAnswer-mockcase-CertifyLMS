<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CoachStudent;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_sees_only_enrollments_in_assigned_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $assignedCert = Certification::factory()->published()->create(['name' => 'Assigned Cert']);
        $otherCert = Certification::factory()->published()->create(['name' => 'Other Cert']);

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $assignedCert->id,
        ]);
        Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $otherCert->id,
        ]);

        $response = $this->actingAs($coach)->get(route('coach.students.index'));

        $response->assertOk();
        $response->assertSee('Assigned Cert');
        $response->assertDontSee('Other Cert');
    }

    public function test_admin_cannot_access_coach_students(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('coach.students.index'))
            ->assertForbidden();
    }

    public function test_student_cannot_access_coach_students(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('coach.students.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_access(): void
    {
        $this->get(route('coach.students.index'))
            ->assertRedirect(route('login'));
    }
}
