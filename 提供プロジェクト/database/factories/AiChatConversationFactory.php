<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AI 相談会話のテスト・シーダー用 Factory。
 *
 * - 既定: 全般相談モード (enrollment_id / section_id 共に null)
 * - state withEnrollment: 資格相談モード
 * - state withSection: 教材相談モード (enrollment_id も自動同居)
 *
 * @extends Factory<AiChatConversation>
 */
class AiChatConversationFactory extends Factory
{
    protected $model = AiChatConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'enrollment_id' => null,
            'section_id' => null,
            'title' => fake()->realText(30),
            'last_message_at' => now(),
        ];
    }

    public function withEnrollment(?Enrollment $enrollment = null): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment?->id ?? Enrollment::factory(),
        ]);
    }

    public function withSection(?Section $section = null, ?Enrollment $enrollment = null): static
    {
        return $this->state(fn () => [
            'section_id' => $section?->id ?? Section::factory(),
            'enrollment_id' => $enrollment?->id ?? Enrollment::factory(),
        ]);
    }

    public function general(): static
    {
        return $this->state(fn () => [
            'enrollment_id' => null,
            'section_id' => null,
        ]);
    }
}
