<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMember>
 */
class ChatMemberFactory extends Factory
{
    protected $model = ChatMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'user_id' => User::factory(),
            'last_read_at' => null,
            'joined_at' => now(),
        ];
    }

    public function asStudent(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state(['role' => UserRole::Student->value]),
        ]);
    }

    public function asCoach(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state(['role' => UserRole::Coach->value]),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn () => [
            'last_read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'last_read_at' => now(),
        ]);
    }
}
