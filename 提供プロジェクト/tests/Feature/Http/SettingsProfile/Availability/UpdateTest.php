<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_update_own_availability(): void
    {
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->monday()->morning()->create();

        $response = $this->actingAs($coach)->patch(route('settings.availability.update', $availability), [
            'day_of_week' => 3,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'meeting']));
        $response->assertSessionHas('success', '面談可能時間枠を更新しました。');

        $this->assertDatabaseHas('coach_availabilities', [
            'id' => $availability->id,
            'day_of_week' => 3,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
            'is_active' => 1,
        ]);
    }

    public function test_coach_cannot_update_other_coachs_availability(): void
    {
        $coach = User::factory()->coach()->create();
        $other = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($other)->monday()->create();

        $response = $this->actingAs($coach)->patch(route('settings.availability.update', $availability), [
            'day_of_week' => 3,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'is_active' => '1',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('coach_availabilities', [
            'id' => $availability->id,
            'coach_id' => $other->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_validation_fails_when_end_time_before_start_time(): void
    {
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->monday()->morning()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.availability.index'))
            ->patch(route('settings.availability.update', $availability), [
                'day_of_week' => 1,
                'start_time' => '17:00',
                'end_time' => '12:00',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors(['end_time']);
        $this->assertDatabaseHas('coach_availabilities', [
            'id' => $availability->id,
            'start_time' => '09:00:00',
        ]);
    }

    public function test_student_is_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->monday()->create();
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->patch(route('settings.availability.update', $availability), [
            'day_of_week' => 3,
            'start_time' => '13:00',
            'end_time' => '18:00',
            'is_active' => '1',
        ]);

        $response->assertForbidden();
    }
}
