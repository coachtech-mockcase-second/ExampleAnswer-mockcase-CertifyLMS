<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminAnnouncementTargetType;
use App\Models\AdminAnnouncement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAnnouncement>
 */
class AdminAnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory()->admin(),
            'title' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'target_type' => AdminAnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'dispatched_count' => 0,
            'dispatched_at' => null,
        ];
    }

    public function allStudents(): static
    {
        return $this->state(fn () => [
            'target_type' => AdminAnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
        ]);
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => [
            'target_type' => AdminAnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'target_type' => AdminAnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $user->id,
        ]);
    }

    public function dispatched(int $count = 1): static
    {
        return $this->state(fn () => [
            'dispatched_count' => $count,
            'dispatched_at' => now(),
        ]);
    }
}
