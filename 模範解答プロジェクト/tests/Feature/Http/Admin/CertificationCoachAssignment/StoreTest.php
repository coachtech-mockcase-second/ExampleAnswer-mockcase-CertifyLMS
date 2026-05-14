<?php

namespace Tests\Feature\Http\Admin\CertificationCoachAssignment;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_coach(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.certifications.coaches.store', $cert),
            ['coach_user_id' => $coach->id]
        );

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertDatabaseHas('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'coach_user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
        ]);
    }

    public function test_cannot_assign_non_coach_user(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.certifications.coaches.store', $cert),
            ['coach_user_id' => $student->id]
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'coach_user_id' => $student->id,
        ]);
    }

    public function test_assigning_same_coach_twice_is_noop(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)->post(
            route('admin.certifications.coaches.store', $cert),
            ['coach_user_id' => $coach->id]
        );

        $this->actingAs($admin)->post(
            route('admin.certifications.coaches.store', $cert),
            ['coach_user_id' => $coach->id]
        );

        $this->assertSame(
            1,
            \DB::table('certification_coach_assignments')
                ->where('certification_id', $cert->id)
                ->where('coach_user_id', $coach->id)
                ->count()
        );
    }

    public function test_coach_cannot_assign(): void
    {
        $auth = User::factory()->coach()->create();
        $targetCoach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($auth)->post(
            route('admin.certifications.coaches.store', $cert),
            ['coach_user_id' => $targetCoach->id]
        );

        $response->assertForbidden();
    }
}
