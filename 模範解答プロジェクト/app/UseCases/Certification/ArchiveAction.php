<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveAction
{
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Published) {
            throw new CertificationInvalidTransitionException(
                from: $certification->status,
                to: CertificationStatus::Archived,
            );
        }

        return DB::transaction(function () use ($certification, $admin) {
            $certification->update([
                'status' => CertificationStatus::Archived->value,
                'archived_at' => now(),
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
