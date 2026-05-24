<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationCoachAssignment>
 */
class CertificationCoachAssignmentFactory extends Factory
{
    protected $model = CertificationCoachAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certification_id' => Certification::factory(),
            'user_id' => User::factory()->coach(),
            'assigned_by_user_id' => User::factory()->admin(),
            'assigned_at' => now(),
            'unassigned_at' => null,
        ];
    }

    /**
     * 担当解除済み(履歴) の割当。
     */
    public function unassigned(): static
    {
        return $this->state(fn () => [
            'unassigned_at' => now(),
        ]);
    }
}
