<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタをアーカイブ（published → archived）するユースケース。
 * 公開中以外の状態からの呼出は CertificationInvalidTransitionException（409）。
 */
final class ArchiveAction
{
    /**
     * @throws CertificationInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Published) {
            throw CertificationInvalidTransitionException::forArchive();
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
