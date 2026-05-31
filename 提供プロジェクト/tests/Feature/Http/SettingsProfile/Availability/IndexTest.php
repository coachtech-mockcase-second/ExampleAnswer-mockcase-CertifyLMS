<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->get(route('settings.availability.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_coach_is_redirected_to_profile_meeting_tab(): void
    {
        $coach = User::factory()->coach()->create();
        CoachAvailability::factory()->forCoach($coach)->monday()->morning()->create();

        $response = $this->actingAs($coach)->get(route('settings.availability.index'));

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'meeting']));
    }

    public function test_coach_meeting_tab_renders_own_availabilities_in_view_data(): void
    {
        $coach = User::factory()->coach()->create();
        $monday = CoachAvailability::factory()->forCoach($coach)->monday()->morning()->create();
        $mondayEve = CoachAvailability::factory()->forCoach($coach)->monday()->evening()->create();
        $sunday = CoachAvailability::factory()->forCoach($coach)->sunday()->morning()->create();

        $response = $this->actingAs($coach)->get(route('settings.profile.edit', ['tab' => 'meeting']));

        $response->assertOk();
        $response->assertViewIs('settings.profile');

        $availabilities = $response->viewData('availabilities');
        $this->assertSame(3, $availabilities->count());

        $this->assertSame([
            $sunday->id,
            $monday->id,
            $mondayEve->id,
        ], $availabilities->pluck('id')->all());
    }

    public function test_coach_does_not_see_other_coaches_availabilities(): void
    {
        $coach = User::factory()->coach()->create();
        $other = User::factory()->coach()->create();
        CoachAvailability::factory()->forCoach($other)->monday()->create();

        $response = $this->actingAs($coach)->get(route('settings.profile.edit', ['tab' => 'meeting']));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('availabilities')->count());
    }

    public function test_student_is_forbidden_on_availability_endpoint(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('settings.availability.index'));

        $response->assertForbidden();
    }

    public function test_admin_is_forbidden_on_availability_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('settings.availability.index'));

        $response->assertForbidden();
    }
}
