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
            'from_status' => UserStatus::Invited->value,
            'to_status' => UserStatus::InProgress->value,
            'changed_by_user_id' => User::factory()->admin(),
            'changed_at' => now(),
            'changed_reason' => null,
        ];
    }

    public function bySystem(): static
    {
        return $this->state(fn () => ['changed_by_user_id' => null]);
    }

    public function from(UserStatus $status): static
    {
        return $this->state(fn () => ['from_status' => $status->value]);
    }

    public function to(UserStatus $status): static
    {
        return $this->state(fn () => ['to_status' => $status->value]);
    }

    public function byAdmin(User $admin): static
    {
        return $this->state(fn () => ['changed_by_user_id' => $admin->id]);
    }
}
