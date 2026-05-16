<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->invited();

        return [
            'user_id' => $user,
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Student->value,
            'invited_by_user_id' => User::factory()->admin(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
            'status' => InvitationStatus::Pending->value,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Pending->value,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Accepted->value,
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Expired->value,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Revoked->value,
            'revoked_at' => now(),
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn () => ['role' => $role->value]);
    }

    public function expiringAt(\DateTimeInterface $expiresAt): static
    {
        return $this->state(fn () => ['expires_at' => $expiresAt]);
    }
}
