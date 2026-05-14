<?php

namespace Tests\Feature\Http\Admin\CertificationCoachAssignment;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_coach_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $cert->coaches()->syncWithoutDetaching([
            $coach->id => [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.certifications.coaches.destroy', [$cert, $coach]));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertDatabaseMissing('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'coach_user_id' => $coach->id,
        ]);
    }

    public function test_coach_cannot_remove(): void
    {
        $auth = User::factory()->coach()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($auth)->delete(route('admin.certifications.coaches.destroy', [$cert, $coach]));

        $response->assertForbidden();
    }
}
