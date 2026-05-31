<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatRoom;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatRoom>
 */
class ChatRoomFactory extends Factory
{
    protected $model = ChatRoom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'last_message_at' => null,
        ];
    }

    public function coachUnassigned(): static
    {
        return $this->state(fn () => [
            'last_message_at' => null,
        ]);
    }

    public function withMessageAt(\DateTimeInterface $at): static
    {
        return $this->state(fn () => [
            'last_message_at' => $at,
        ]);
    }
}
