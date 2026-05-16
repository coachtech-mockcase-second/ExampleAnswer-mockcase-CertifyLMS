<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Draft) {
            throw new CertificationInvalidTransitionException(
                from: $certification->status,
                to: CertificationStatus::Published,
            );
        }

        return DB::transaction(function () use ($certification, $admin) {
            $certification->update([
                'status' => CertificationStatus::Published->value,
                'published_at' => now(),
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
