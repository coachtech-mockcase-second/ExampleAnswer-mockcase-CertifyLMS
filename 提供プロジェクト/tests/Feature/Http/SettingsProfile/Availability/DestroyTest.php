<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_destroy_own_availability(): void
    {
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->monday()->create();

        $response = $this->actingAs($coach)->delete(route('settings.availability.destroy', $availability));

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'meeting']));
        $response->assertSessionHas('success', '面談可能時間枠を削除しました。');

        $this->assertDatabaseMissing('coach_availabilities', ['id' => $availability->id]);
    }

    public function test_coach_cannot_destroy_other_coachs_availability(): void
    {
        $coach = User::factory()->coach()->create();
        $other = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($other)->monday()->create();

        $response = $this->actingAs($coach)->delete(route('settings.availability.destroy', $availability));

        $response->assertForbidden();
        $this->assertDatabaseHas('coach_availabilities', ['id' => $availability->id]);
    }

    public function test_student_is_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->monday()->create();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->delete(route('settings.availability.destroy', $availability));

        $response->assertForbidden();
    }
}
