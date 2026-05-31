<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingMemo>
 */
class MeetingMemoFactory extends Factory
{
    protected $model = MeetingMemo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'body' => fake()->paragraphs(2, true),
        ];
    }

    public function forMeeting(Meeting $meeting): static
    {
        return $this->state(fn () => [
            'meeting_id' => $meeting->id,
        ]);
    }
}
