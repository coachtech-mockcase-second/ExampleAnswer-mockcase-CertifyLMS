<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachGoogleCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachGoogleCredential>
 */
class CoachGoogleCredentialFactory extends Factory
{
    protected $model = CoachGoogleCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coach_id' => User::factory()->coach(),
            'access_token' => 'ya29.fake_access_'.fake()->lexify('????????'),
            'refresh_token' => '1//fake_refresh_'.fake()->lexify('????????'),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ];
    }

    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'coach_id' => $coach->id,
        ]);
    }
}
