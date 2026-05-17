<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStatusLog>
 */
class UserStatusLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'changed_by_user_id' => User::factory()->admin(),
            'status' => UserStatus::InProgress->value,
            'changed_at' => now(),
            'changed_reason' => null,
        ];
    }

    public function bySystem(): static
    {
        return $this->state(fn () => ['changed_by_user_id' => null]);
    }

    public function status(UserStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
