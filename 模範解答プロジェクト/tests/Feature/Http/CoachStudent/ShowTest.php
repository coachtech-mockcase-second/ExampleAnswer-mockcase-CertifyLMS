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

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_see_assigned_enrollment_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create(['name' => '受講生 太郎']);

        $cert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $cert->id,
        ]);

        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertSee('受講生 太郎');
        $response->assertSee($cert->name);
    }

    public function test_coach_cannot_see_unassigned_enrollment(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $unassignedCert = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $unassignedCert->id,
        ]);

        $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment))
            ->assertForbidden();
    }
}
