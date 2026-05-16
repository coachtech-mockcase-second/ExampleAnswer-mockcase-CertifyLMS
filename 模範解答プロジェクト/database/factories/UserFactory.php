<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Student->value,
            'status' => UserStatus::Active->value,
            'profile_setup_completed' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin->value]);
    }

    public function coach(): static
    {
        return $this->state(fn () => ['role' => UserRole::Coach->value]);
    }

    public function student(): static
    {
        return $this->state(fn () => ['role' => UserRole::Student->value]);
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            'name' => null,
            'password' => null,
            'status' => UserStatus::Invited->value,
            'profile_setup_completed' => false,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Withdrawn->value]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
