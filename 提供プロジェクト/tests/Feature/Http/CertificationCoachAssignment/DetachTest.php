<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CertificationCoachAssignment;

use App\Events\CertificationCoachDetached;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class DetachTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_detach_coach(): void
    {
        Event::fake();

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->delete(
            route('admin.certifications.coaches.detach', ['certification' => $cert, 'coach' => $coach])
        );

        $response->assertRedirect(route('admin.certifications.show', $cert));

        $this->assertDatabaseHas('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
        ]);
        $assignment = CertificationCoachAssignment::query()
            ->where('certification_id', $cert->id)
            ->where('user_id', $coach->id)
            ->first();
        $this->assertNotNull($assignment);
        $this->assertNotNull($assignment->unassigned_at);

        Event::assertDispatched(CertificationCoachDetached::class);
    }

    public function test_coach_cannot_detach(): void
    {
        $auth = User::factory()->coach()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($auth)->delete(
            route('admin.certifications.coaches.detach', ['certification' => $cert, 'coach' => $coach])
        );

        $response->assertForbidden();
    }
}
