<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'enrollment_id' => Enrollment::factory()->passed(),
            'certification_id' => Certification::factory()->published(),
            'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
            'issued_at' => now(),
        ];
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'user_id' => $enrollment->user_id,
            'enrollment_id' => $enrollment->id,
            'certification_id' => $enrollment->certification_id,
        ]);
    }
}
