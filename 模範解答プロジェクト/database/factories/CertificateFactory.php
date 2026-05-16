<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
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
            'serial_no' => 'CT-'.now()->format('Ym').'-'.Str::upper(Str::random(5)),
            'issued_at' => now(),
            'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
            'issued_by_user_id' => User::factory()->admin(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Certificate $certificate) {
            //
        })->afterCreating(function (Certificate $certificate) {
            //
        });
    }

    public function withSerial(string $serialNo): static
    {
        return $this->state(fn () => ['serial_no' => $serialNo]);
    }

    public function serials(): static
    {
        return $this->state(new Sequence(
            fn (Sequence $sequence) => [
                'serial_no' => 'CT-'.now()->format('Ym').'-'.str_pad((string) ($sequence->index + 1), 5, '0', STR_PAD_LEFT),
            ],
        ));
    }
}
