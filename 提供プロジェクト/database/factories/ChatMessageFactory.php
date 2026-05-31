<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'sender_user_id' => User::factory(),
            'body' => fake()->realText(120),
        ];
    }

    public function fromStudent(): static
    {
        return $this->state(fn () => [
            'sender_user_id' => User::factory()->state(['role' => UserRole::Student->value]),
        ]);
    }

    public function fromCoach(): static
    {
        return $this->state(fn () => [
            'sender_user_id' => User::factory()->state(['role' => UserRole::Coach->value]),
        ]);
    }
}
