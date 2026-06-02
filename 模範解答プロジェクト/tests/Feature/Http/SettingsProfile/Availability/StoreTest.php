<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Availability;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_create_availability(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('settings.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success', '面談可能時間枠を追加しました。');

        $this->assertDatabaseHas('coach_availabilities', [
            'coach_id' => $coach->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_active' => 1,
        ]);
    }

    public function test_coach_can_create_availability_with_is_active_false_when_checkbox_unchecked(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->post(route('settings.availability.store'), [
            'day_of_week' => 2,
            'start_time' => '14:00',
            'end_time' => '17:00',
        ]);

        $this->assertDatabaseHas('coach_availabilities', [
            'coach_id' => $coach->id,
            'day_of_week' => 2,
            'is_active' => 0,
        ]);
    }

    public function test_multiple_overlapping_slots_on_same_day_are_allowed(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->post(route('settings.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => '1',
        ]);
        $this->actingAs($coach)->post(route('settings.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '15:00',
            'is_active' => '1',
        ]);

        $this->assertSame(2, $coach->coachAvailabilities()->count());
    }

    public function test_validation_fails_when_end_time_is_before_start_time(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.availability.index'))
            ->post(route('settings.availability.store'), [
                'day_of_week' => 1,
                'start_time' => '17:00',
                'end_time' => '09:00',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors(['end_time']);
        $this->assertSame(0, $coach->coachAvailabilities()->count());
    }

    public function test_validation_fails_when_day_of_week_is_out_of_range(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.availability.index'))
            ->post(route('settings.availability.store'), [
                'day_of_week' => 7,
                'start_time' => '09:00',
                'end_time' => '12:00',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors(['day_of_week']);
    }

    public function test_validation_fails_when_start_time_format_is_invalid(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.availability.index'))
            ->post(route('settings.availability.store'), [
                'day_of_week' => 1,
                'start_time' => '9 am',
                'end_time' => '12:00',
                'is_active' => '1',
            ]);

        $response->assertSessionHasErrors(['start_time']);
    }

    public function test_student_is_forbidden(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->post(route('settings.availability.store'), [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $response->assertForbidden();
    }
}
