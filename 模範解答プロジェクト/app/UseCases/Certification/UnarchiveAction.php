<?php

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnarchiveAction
{
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Archived) {
            throw new CertificationInvalidTransitionException(
                from: $certification->status,
                to: CertificationStatus::Draft,
            );
        }

        return DB::transaction(function () use ($certification, $admin) {
            $certification->update([
                'status' => CertificationStatus::Draft->value,
                'published_at' => null,
                'archived_at' => null,
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
