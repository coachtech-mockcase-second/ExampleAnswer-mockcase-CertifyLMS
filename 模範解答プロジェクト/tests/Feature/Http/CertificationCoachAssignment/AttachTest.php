<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CertificationCoachAssignment;

use App\Events\CertificationCoachAttached;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttachTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_attach_coach(): void
    {
        Event::fake();

        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $coach])
        );

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertDatabaseHas('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'unassigned_at' => null,
        ]);

        Event::assertDispatched(CertificationCoachAttached::class, function (CertificationCoachAttached $event) use ($cert, $coach) {
            return $event->certification->is($cert) && $event->coach->is($coach);
        });
    }

    public function test_cannot_attach_non_coach_user(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->postJson(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $student])
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_attaching_same_coach_twice_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)->post(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $coach])
        );

        $this->actingAs($admin)->post(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $coach])
        );

        $this->assertSame(
            1,
            CertificationCoachAssignment::query()
                ->where('certification_id', $cert->id)
                ->where('user_id', $coach->id)
                ->whereNull('unassigned_at')
                ->count()
        );
    }

    public function test_reattaching_after_detach_restores_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now()->subDays(10),
            'unassigned_at' => now()->subDay(),
            'deleted_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $coach])
        );

        $this->assertDatabaseHas('certification_coach_assignments', [
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'unassigned_at' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_coach_cannot_attach(): void
    {
        $auth = User::factory()->coach()->create();
        $targetCoach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($auth)->post(
            route('admin.certifications.coaches.attach', ['certification' => $cert, 'coach' => $targetCoach])
        );

        $response->assertForbidden();
    }
}
